<?php

declare(strict_types=1);

namespace Crm\User\Tests\UI;

use Crm\User\Application\CreateTeam;
use Crm\User\Application\CreateUser;
use Crm\User\Domain\Role;
use Crm\User\Tests\Double\FakePasswordHasher;
use Crm\User\Tests\Double\InMemoryTeamRepository;
use Crm\User\Tests\Double\InMemoryUserRepository;
use Crm\User\UI\Console\SeedUsersCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedUsersCommandTest extends TestCase
{
    #[Test]
    public function it_creates_the_development_administrator(): void
    {
        [$tester, $users] = $this->command('dev');

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $admin = $users->findByEmail(SeedUsersCommand::DEV_EMAIL);
        self::assertNotNull($admin);
        self::assertTrue($admin->hasRole(Role::ADMIN));
        self::assertSame(SeedUsersCommand::DEV_TEAM, $admin->team()?->name());
    }

    #[Test]
    public function it_does_nothing_when_users_already_exist(): void
    {
        // make fresh ruft den Befehl bei jedem Lauf auf.
        [$tester, $users] = $this->command('dev');
        $tester->execute([]);

        $tester->execute([]);

        self::assertSame(1, $users->countAll());
        self::assertStringContainsString('bereits Benutzer vorhanden', $tester->getDisplay());
    }

    #[Test]
    public function it_refuses_to_run_in_production(): void
    {
        // Ein Konto mit fest verdrahtetem Passwort gehoert nicht in
        // Produktion - und zwar erzwungen, nicht per Hinweis im README.
        [$tester, $users] = $this->command('prod');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame(0, $users->countAll());
        self::assertStringContainsString('nur in dev und test', $tester->getDisplay());
    }

    #[Test]
    public function it_runs_in_the_test_environment(): void
    {
        [$tester, $users] = $this->command('test');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(1, $users->countAll());
    }

    /**
     * @return array{0: CommandTester, 1: InMemoryUserRepository}
     */
    private function command(string $environment): array
    {
        $users = new InMemoryUserRepository();
        $teams = new InMemoryTeamRepository();

        $application = new Application();
        $application->addCommand(new SeedUsersCommand(
            new CreateUser($users, $teams, new FakePasswordHasher()),
            new CreateTeam($teams),
            $users,
            $environment,
        ));

        return [new CommandTester($application->find('user:seed')), $users];
    }
}
