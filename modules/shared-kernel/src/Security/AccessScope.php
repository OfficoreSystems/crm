<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * How far a right reaches.
 *
 * Not "may / may not" but "may for what": a salesperson may edit deals - but
 * only their own. Without this gradation every combination would need a role of
 * its own.
 *
 * There is no case for "not at all": that is the absence of an entry in the
 * matrix. An explicit NONE case would be a second way of saying the same thing -
 * and eventually both would sit next to each other.
 */
enum AccessScope: string
{
    /**
     * Own records only.
     */
    case OWN = 'own';

    /**
     * Everything from one's own team.
     */
    case TEAM = 'team';

    /**
     * Everything.
     */
    case ALL = 'all';

    /**
     * A translation key, not a finished text - see Stage and ActivityType for
     * the same pattern.
     */
    public function label(): string
    {
        return match ($this) {
            self::OWN => 'security.scope.own',
            self::TEAM => 'security.scope.team',
            self::ALL => 'security.scope.all',
        };
    }

    /**
     * The higher, the further-reaching.
     *
     * Needed when a user holds several roles: the widest one wins. Otherwise an
     * additional role could *take away* rights, which nobody expects.
     */
    public function rank(): int
    {
        return match ($this) {
            self::OWN => 1,
            self::TEAM => 2,
            self::ALL => 3,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * The widest one from a set, or null when the set is empty.
     *
     * @param list<self> $scopes
     */
    public static function widest(array $scopes): ?self
    {
        $widest = null;

        foreach ($scopes as $scope) {
            if (null === $widest || $scope->rank() > $widest->rank()) {
                $widest = $scope;
            }
        }

        return $widest;
    }
}
