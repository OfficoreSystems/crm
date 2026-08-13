<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Who owns a record.
 *
 * Both fields may be null - a record without an owner is a normal state, for
 * instance with imported data. It is then reachable only with ALL rights, which
 * is the safe default.
 */
final readonly class RecordOwnership
{
    public function __construct(
        public ?string $ownerId = null,
        public ?string $teamId = null,
    ) {
    }

    public static function nobody(): self
    {
        return new self();
    }

    public function isOwnedBy(ActorInterface $actor): bool
    {
        return null !== $this->ownerId && $this->ownerId === $actor->actorId();
    }

    /**
     * Does the record belong to the actor's team?
     *
     * Two null teams do not count as the same team. Otherwise a user without a
     * team would see the data of every other user without a team.
     */
    public function belongsToTeamOf(ActorInterface $actor): bool
    {
        return null !== $this->teamId
            && null !== $actor->actorTeamId()
            && $this->teamId === $actor->actorTeamId();
    }
}
