<?php

declare(strict_types=1);

namespace Crm\User\Tests\Double;

use Crm\User\Domain\Team;
use Crm\User\Domain\TeamRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class InMemoryTeamRepository implements TeamRepositoryInterface
{
    /**
     * @var array<string, Team>
     */
    private array $teams = [];

    public function save(Team $team): void
    {
        $this->teams[(string) $team->id()] = $team;
    }

    public function remove(Team $team): void
    {
        unset($this->teams[(string) $team->id()]);
    }

    public function find(Uuid $id): ?Team
    {
        return $this->teams[(string) $id] ?? null;
    }

    public function findByName(string $name): ?Team
    {
        $needle = trim($name);

        foreach ($this->teams as $team) {
            if ($team->name() === $needle) {
                return $team;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        $all = array_values($this->teams);
        usort($all, static fn (Team $a, Team $b): int => $a->name() <=> $b->name());

        return $all;
    }

    public function countAll(): int
    {
        return \count($this->teams);
    }
}
