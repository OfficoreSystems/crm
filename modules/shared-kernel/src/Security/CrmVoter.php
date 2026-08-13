<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Der eine Voter fuer alles.
 *
 * Er kennt kein Modul. Er liest aus dem Attribut, um welches Modul und welche
 * Aktion es geht, fragt die OwnershipRegistry nach dem Besitzer des
 * Datensatzes, schlaegt in der Rechtematrix nach und vergleicht. Ein neues
 * Modul wird geschuetzt, indem es einen RecordOwnership-Anbieter mitbringt -
 * hier aendert sich nichts.
 *
 * Das Attribut hat die Form "modul.aktion":
 *
 *     #[IsGranted('deal.view')]                    Listenseite: darf er ueberhaupt?
 *     #[IsGranted('deal.edit', subject: 'deal')]   Datensatz: darf er *diesen*?
 *
 * Das Modul steckt im Attribut und nicht im Subjekt, weil Symfony ein Subjekt
 * vom Typ String als *Argumentnamen* des Controllers deutet. Fuer eine
 * Listenseite gibt es kein solches Argument - der Umweg ueber einen
 * Expression-Ausdruck waere die Alternative gewesen und haette jede
 * Controller-Zeile unleserlich gemacht.
 *
 * @extends Voter<string, object|null>
 */
final class CrmVoter extends Voter
{
    /**
     * modul.aktion, beides klein geschrieben.
     */
    private const ATTRIBUTE = '/^([a-z][a-z0-9-]{1,39})\.([a-z]+)$/';

    public function __construct(
        private readonly PermissionMatrix $matrix,
        private readonly OwnershipRegistry $ownership,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== self::parse($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $parsed = self::parse($attribute);

        if (null === $parsed) {
            return false;
        }

        [$module, $action] = $parsed;
        $actor = $token->getUser();

        if (!$actor instanceof ActorInterface) {
            // Nicht angemeldet, oder ein Benutzertyp, der sich nicht als
            // Handelnder ausweist. Beides heisst: nein.
            return false;
        }

        $scope = $this->matrix->scopeFor($actor->actorRoles(), $module, $action);

        if (null === $scope) {
            return false;
        }

        if (AccessScope::ALL === $scope) {
            return true;
        }

        // Ohne Datensatz laesst sich Besitz nicht pruefen. Die Frage lautet
        // dann "darf er ueberhaupt" - und ein eingeschraenktes Recht ist ein
        // Recht. Ob er *diesen* Datensatz darf, entscheidet der Aufruf mit
        // dem Objekt.
        if (!\is_object($subject)) {
            return true;
        }

        $ownership = $this->ownership->ownershipOf($subject);

        if (AccessScope::TEAM === $scope) {
            return $ownership->isOwnedBy($actor) || $ownership->belongsToTeamOf($actor);
        }

        // Bleibt OWN - ALL ist oben abgehandelt.
        return $ownership->isOwnedBy($actor);
    }

    /**
     * @return array{0: string, 1: Action}|null
     */
    private static function parse(string $attribute): ?array
    {
        if (1 !== preg_match(self::ATTRIBUTE, $attribute, $matches)) {
            return null;
        }

        $action = Action::tryFrom($matches[2]);

        return null === $action ? null : [$matches[1], $action];
    }
}
