<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Der eine Voter fuer alles.
 *
 * Er kennt kein Modul. Er fragt die OwnershipRegistry, zu welchem Modul ein
 * Datensatz gehoert und wem er gehoert, schlaegt in der Rechtematrix nach, was
 * die Rollen des Benutzers dort duerfen, und vergleicht beides. Ein neues
 * Modul wird geschuetzt, indem es einen RecordOwnership-Anbieter mitbringt -
 * hier aendert sich nichts.
 *
 * Er versteht zwei Formen von Subjekt:
 *
 *   #[IsGranted('view', 'deal')]     - darf der Benutzer Deals ueberhaupt sehen?
 *   #[IsGranted('edit', subject: 'deal')] mit einem Deal-Objekt - darf er *diesen*?
 *
 * Die erste Form ist fuer Listenseiten gedacht, die zweite fuer Datensaetze.
 * Beide gehen ueber dieselbe Matrix, damit es keine zweite Wahrheit gibt.
 *
 * @extends Voter<string, object|string|null>
 */
final class CrmVoter extends Voter
{
    public function __construct(
        private readonly PermissionMatrix $matrix,
        private readonly OwnershipRegistry $ownership,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (null === Action::tryFrom($attribute)) {
            return false;
        }

        // Modulname als Zeichenkette, oder ein Datensatz, fuer den sich ein
        // Modul zustaendig fuehlt.
        return \is_string($subject) || (\is_object($subject) && $this->ownership->supports($subject));
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $actor = $token->getUser();

        if (!$actor instanceof ActorInterface) {
            // Nicht angemeldet, oder ein Benutzertyp, der sich nicht als
            // Handelnder ausweist. Beides heisst: nein.
            return false;
        }

        if (\is_string($subject)) {
            $module = $subject;
            $record = null;
        } elseif (\is_object($subject)) {
            $module = $this->ownership->moduleOf($subject);
            $record = $subject;
        } else {
            return false;
        }

        if (null === $module) {
            return false;
        }

        $scope = $this->matrix->scopeFor($actor->actorRoles(), $module, Action::from($attribute));

        if (null === $scope) {
            return false;
        }

        if (AccessScope::ALL === $scope) {
            return true;
        }

        // Ohne Datensatz laesst sich Besitz nicht pruefen. Die Frage lautet
        // dann "darf er ueberhaupt" - und ein eingeschraenktes Recht ist ein
        // Recht. Ob er *diesen* Datensatz darf, entscheidet der zweite Aufruf
        // mit dem Objekt.
        if (null === $record) {
            return true;
        }

        $ownership = $this->ownership->ownershipOf($record);

        if (AccessScope::TEAM === $scope) {
            return $ownership->isOwnedBy($actor) || $ownership->belongsToTeamOf($actor);
        }

        // Bleibt OWN - ALL ist oben abgehandelt.
        return $ownership->isOwnedBy($actor);
    }
}
