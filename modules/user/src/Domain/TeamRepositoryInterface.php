<?php

declare(strict_types=1);

namespace Crm\User\Domain;

use Symfony\Component\Uid\Uuid;

interface TeamRepositoryInterface
{
    public function save(Team $team): void;

    public function remove(Team $team): void;

    public function find(Uuid $id): ?Team;

    public function findByName(string $name): ?Team;

    /**
     * @return list<Team>
     */
    public function findAll(): array;

    public function countAll(): int;
}
