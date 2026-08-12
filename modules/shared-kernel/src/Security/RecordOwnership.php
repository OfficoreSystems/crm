<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Wem ein Datensatz gehoert.
 *
 * Beide Felder duerfen null sein - ein Datensatz ohne Besitzer ist ein
 * normaler Zustand, etwa bei importierten Daten. Er ist dann nur mit
 * ALL-Rechten erreichbar, was die sichere Voreinstellung ist.
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
     * Gehoert der Datensatz zum Team des Handelnden?
     *
     * Zwei null-Teams gelten nicht als dasselbe Team. Sonst saehe ein
     * teamloser Benutzer alle Daten anderer teamloser Benutzer.
     */
    public function belongsToTeamOf(ActorInterface $actor): bool
    {
        return null !== $this->teamId
            && null !== $actor->actorTeamId()
            && $this->teamId === $actor->actorTeamId();
    }
}
