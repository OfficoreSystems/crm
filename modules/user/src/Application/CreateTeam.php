<?php

declare(strict_types=1);

namespace Crm\User\Application;

use Crm\User\Domain\Team;
use Crm\User\Domain\TeamRepositoryInterface;

final readonly class CreateTeam
{
    public function __construct(
        private TeamRepositoryInterface $teams,
    ) {
    }

    /**
     * Idempotent: ein Team mit gleichem Namen wird zurueckgegeben statt
     * ein zweites anzulegen. Der Name ist in der Datenbank unique, ein
     * blindes Anlegen wuerde also ohnehin scheitern.
     */
    public function __invoke(string $name): Team
    {
        $existing = $this->teams->findByName($name);

        if (null !== $existing) {
            return $existing;
        }

        $team = Team::create($name);
        $this->teams->save($team);

        return $team;
    }
}
