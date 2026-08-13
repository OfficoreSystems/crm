<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The one voter for everything.
 *
 * It knows no module. It reads from the attribute which module and which action
 * are meant, asks the OwnershipRegistry who owns the record, looks the answer up
 * in the permission matrix and compares. A new module gets protected by bringing
 * a RecordOwnership provider along - nothing changes here.
 *
 * The attribute has the form "module.action":
 *
 *     #[IsGranted('deal.view')]                    list page: may he at all?
 *     #[IsGranted('deal.edit', subject: 'deal')]   record: may he have *this* one?
 *
 * The module sits in the attribute and not in the subject, because Symfony reads
 * a subject of type string as the *argument name* of the controller. For a list
 * page there is no such argument - the detour through an expression would have
 * been the alternative and would have made every controller line unreadable.
 *
 * @extends Voter<string, object|null>
 */
final class CrmVoter extends Voter
{
    /**
     * module.action, both lower case.
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
            // Not signed in, or a user type that does not identify itself as an
            // actor. Either way the answer is no.
            return false;
        }

        $scope = $this->matrix->scopeFor($actor->actorRoles(), $module, $action);

        if (null === $scope) {
            return false;
        }

        if (AccessScope::ALL === $scope) {
            return true;
        }

        // Without a record there is no ownership to check. The question is then
        // "may he at all" - and a restricted right is still a right. Whether he
        // may have *this* record is decided by the call that passes the object.
        if (!\is_object($subject)) {
            return true;
        }

        $ownership = $this->ownership->ownershipOf($subject);

        if (AccessScope::TEAM === $scope) {
            return $ownership->isOwnedBy($actor) || $ownership->belongsToTeamOf($actor);
        }

        // OWN is what is left - ALL was handled above.
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
