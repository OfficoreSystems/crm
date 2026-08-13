<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Wo ein Modul Besitzer und Team ablegt.
 *
 * Der Voter entscheidet ueber einzelne Datensaetze. Fuer Listen taugt er nicht:
 * eine Seite mit fuenfzig Zeilen wuerde fuenfzig Mal abstimmen, und schlimmer -
 * die Zeilen waeren zu diesem Zeitpunkt bereits aus der Datenbank geladen. Wer
 * fremde Daten nicht sehen soll, soll sie gar nicht erst bekommen.
 *
 * Dafuer braucht der Sichtbarkeitsfilter Spaltennamen. Sie stehen hier und
 * nicht als Attribut an der Entity: die Domain-Schicht eines Moduls haengt an
 * nichts, und ein Attribut aus dem Shared Kernel waere genau so eine
 * Abhaengigkeit. Sie stehen ausserdem beim Ownership-Anbieter, also an
 * derselben Stelle wie die Antwort "wem gehoert dieser Datensatz" - zwei Orte
 * dafuer waeren zwei Orte, die auseinanderlaufen koennen.
 */
final readonly class RestrictedColumns
{
    /**
     * @param class-string $entityClass Die Entity, an deren Abfragen der
     *                                  Filter sich haengt.
     * @param string       $ownerColumn Spaltenname, nicht Feldname - der
     *                                  Filter schreibt SQL.
     */
    public function __construct(
        public string $entityClass,
        public string $ownerColumn = 'owner_id',
        public string $teamColumn = 'owner_team_id',
    ) {
    }
}
