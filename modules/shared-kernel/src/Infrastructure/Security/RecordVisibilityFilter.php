<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Infrastructure\Security;

use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\RecordRestriction;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Schraenkt Listen direkt in SQL ein.
 *
 * Der Voter beantwortet "darf dieser Benutzer *diesen* Datensatz". Fuer eine
 * Liste ist das die falsche Frage: sie wuerde je Zeile gestellt, und die
 * Zeilen waeren zu dem Zeitpunkt bereits geladen. Wer fremde Verkaufschancen
 * nicht sehen soll, soll sie gar nicht erst aus der Datenbank bekommen.
 *
 * Die Klasse liegt in Infrastructure und nicht neben dem Voter: sie erbt von
 * Doctrine, und der Vertragsteil des Shared Kernel soll frei von Persistenz
 * bleiben. Wer hier etwas aendert, aendert Infrastruktur - nicht den Vertrag.
 *
 * Der Filter tut nichts, solange er nicht aktiviert *und* parametrisiert
 * wurde. Beides erledigt {@see RecordVisibilityConfigurator} je Request. Ohne
 * angemeldeten Benutzer bleibt er aus, damit Konsolenbefehle und Migrationen
 * ungehindert arbeiten.
 */
final class RecordVisibilityFilter extends SQLFilter
{
    public const NAME = 'crm_record_visibility';

    /**
     * @var array<class-string, RecordRestriction>
     */
    private array $restrictions = [];

    /**
     * Wird vom Configurator gesetzt, nachdem Doctrine den Filter gebaut hat.
     *
     * Doctrine erzeugt Filter ohne Container, deshalb dieser Weg statt
     * Konstruktor-Injektion.
     *
     * @param array<class-string, RecordRestriction> $restrictions
     */
    public function useRestrictions(array $restrictions): void
    {
        $this->restrictions = $restrictions;
    }

    /**
     * @param ClassMetadata<object> $targetEntity
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Entities ohne Eintrag werden nicht gefiltert - Stammdaten wie Firmen
        // und Kontakte gehoeren allen.
        $restriction = $this->restrictions[$targetEntity->getName()] ?? null;

        if (null === $restriction) {
            return '';
        }

        $scope = $this->scopeFor($restriction->module);

        if (null === $scope || AccessScope::ALL === $scope) {
            return '';
        }

        $actorId = $this->readParameter('actor_id');

        if (null === $actorId) {
            return '';
        }

        $owner = sprintf('%s.%s = %s', $targetTableAlias, $restriction->ownerColumn, $actorId);

        if (AccessScope::OWN === $scope) {
            return $owner;
        }

        $teamId = $this->readParameter('actor_team_id');

        // Ohne Team bleibt von TEAM nur OWN uebrig. Sonst saehe ein teamloser
        // Benutzer alle Datensaetze ohne Team - also die aller anderen
        // teamlosen Benutzer.
        if (null === $teamId) {
            return $owner;
        }

        return sprintf(
            '(%s OR %s.%s = %s)',
            $owner,
            $targetTableAlias,
            $restriction->teamColumn,
            $teamId,
        );
    }

    private function scopeFor(string $module): ?AccessScope
    {
        // Roh, nicht gequotet: der Wert wird verglichen, nicht in SQL
        // eingebettet. Mit Anfuehrungszeichen scheiterte tryFrom() still und
        // der Filter liesse alles durch.
        $raw = $this->readRawParameter('scope_'.$module) ?? $this->readRawParameter('scope_default');

        return null === $raw ? null : AccessScope::tryFrom($raw);
    }

    /**
     * Liest einen Parameter, ohne zu werfen, wenn er fehlt.
     *
     * SQLFilter::getParameter() wirft bei fehlenden Parametern. Genau das ist
     * hier der Normalfall: in der Konsole gibt es keinen angemeldeten
     * Benutzer, und dann soll der Filter schlicht nichts tun.
     */
    private function readParameter(string $name): ?string
    {
        if (!$this->hasParameter($name)) {
            return null;
        }

        // getParameter() liefert den Wert bereits gequotet - genau richtig
        // fuer die Einbettung in SQL.
        $value = $this->getParameter($name);

        return "''" === $value ? null : $value;
    }

    /**
     * Wie readParameter, aber ohne Quoting - fuer Werte, die verglichen und
     * nicht eingebettet werden.
     */
    private function readRawParameter(string $name): ?string
    {
        $quoted = $this->readParameter($name);

        return null === $quoted ? null : trim($quoted, "'");
    }
}
