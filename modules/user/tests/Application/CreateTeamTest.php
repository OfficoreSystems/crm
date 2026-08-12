<?php

declare(strict_types=1);

namespace Crm\User\Tests\Application;

use Crm\User\Application\CreateTeam;
use Crm\User\Tests\Double\InMemoryTeamRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateTeamTest extends TestCase
{
    #[Test]
    public function it_creates_a_team(): void
    {
        $teams = new InMemoryTeamRepository();

        $team = (new CreateTeam($teams))('Vertrieb');

        self::assertSame('Vertrieb', $team->name());
        self::assertSame(1, $teams->countAll());
    }

    #[Test]
    public function it_returns_the_existing_team_instead_of_a_duplicate(): void
    {
        // Der Name ist in der Datenbank unique. Ein blindes Anlegen wuerde
        // also ohnehin scheitern - hier ist der Aufruf idempotent.
        $teams = new InMemoryTeamRepository();
        $createTeam = new CreateTeam($teams);

        $first = $createTeam('Vertrieb');
        $second = $createTeam('Vertrieb');

        self::assertSame($first, $second);
        self::assertSame(1, $teams->countAll());
    }

    #[Test]
    public function it_rejects_a_blank_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CreateTeam(new InMemoryTeamRepository()))('   ');
    }
}
