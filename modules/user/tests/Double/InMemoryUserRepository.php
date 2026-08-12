<?php

declare(strict_types=1);

namespace Crm\User\Tests\Double;

use Crm\User\Domain\User;
use Crm\User\Domain\UserRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[(string) $user->id()] = $user;
    }

    public function remove(User $user): void
    {
        unset($this->users[(string) $user->id()]);
    }

    public function find(Uuid $id): ?User
    {
        return $this->users[(string) $id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        $needle = mb_strtolower(trim($email));

        foreach ($this->users as $user) {
            if ($user->email() === $needle) {
                return $user;
            }
        }

        return null;
    }

    public function emailExists(string $email): bool
    {
        return null !== $this->findByEmail($email);
    }

    public function search(string $query, int $limit = 50): array
    {
        $needle = mb_strtolower(trim($query));

        $matches = array_values(array_filter(
            $this->users,
            static function (User $user) use ($needle): bool {
                if ('' === $needle) {
                    return true;
                }

                return str_contains(mb_strtolower($user->name().' '.$user->email()), $needle);
            },
        ));

        usort($matches, static fn (User $a, User $b): int => $a->name() <=> $b->name());

        return \array_slice($matches, 0, max(1, $limit));
    }

    public function findAllActive(): array
    {
        $active = array_values(array_filter(
            $this->users,
            static fn (User $user): bool => $user->isActive(),
        ));

        usort($active, static fn (User $a, User $b): int => $a->name() <=> $b->name());

        return $active;
    }

    public function countAll(): int
    {
        return \count($this->users);
    }
}
