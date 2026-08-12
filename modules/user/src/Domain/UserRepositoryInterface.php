<?php

declare(strict_types=1);

namespace Crm\User\Domain;

use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function remove(User $user): void;

    public function find(Uuid $id): ?User;

    public function findByEmail(string $email): ?User;

    public function emailExists(string $email): bool;

    /**
     * @return list<User>
     */
    public function search(string $query, int $limit = 50): array;

    /**
     * @return list<User>
     */
    public function findAllActive(): array;

    public function countAll(): int;
}
