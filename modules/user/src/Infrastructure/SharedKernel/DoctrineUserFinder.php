<?php

declare(strict_types=1);

namespace Crm\User\Infrastructure\SharedKernel;

use Crm\SharedKernel\User\UserFinderInterface;
use Crm\SharedKernel\User\UserSummary;
use Crm\User\Domain\User;
use Crm\User\Domain\UserRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Bedient den Extension-Point des Shared Kernel.
 *
 * Damit koennen andere Module Benutzer anzeigen, ohne dieses Modul zu kennen -
 * sie sehen nur {@see UserSummary}, nie die Entity.
 */
final readonly class DoctrineUserFinder implements UserFinderInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function find(string $id): ?UserSummary
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $user = $this->users->find(Uuid::fromString($id));

        return null === $user ? null : self::toSummary($user);
    }

    public function findMany(array $ids): array
    {
        $summaries = [];

        foreach ($ids as $id) {
            $summary = $this->find($id);

            if (null !== $summary) {
                $summaries[$id] = $summary;
            }
        }

        return $summaries;
    }

    public function findAllActive(): array
    {
        return array_map(self::toSummary(...), $this->users->findAllActive());
    }

    private static function toSummary(User $user): UserSummary
    {
        return new UserSummary(
            id: (string) $user->id(),
            name: $user->name(),
            email: $user->email(),
            teamId: $user->teamId()?->toString(),
            active: $user->isActive(),
        );
    }
}
