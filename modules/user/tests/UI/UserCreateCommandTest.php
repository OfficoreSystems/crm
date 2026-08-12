<?php

declare(strict_types=1);

namespace Crm\User\Tests\UI;

use Crm\User\Application\CreateTeam;
use Crm\User\Application\CreateUser;
use Crm\User\Domain\Role;
use Crm\User\Tests\Double\FakePasswordHasher;
use Crm\User\Tests\Double\InMemoryTeamRepository;
use Crm\User\Tests\Double\InMemoryUserRepository;
use Crm\User\UI\Console\UserCreateCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCreateCommandTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryTeamRepository $teams;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->teams = new InMemoryTeamRepository();

        $application = new Application();
        $application->addCommand(new UserCreateCommand(
            new CreateUser($this->users, $this->teams, new FakePasswordHasher()),
            new CreateTeam($this->teams),
        ));

        $this->tester = new CommandTester($application->find('user:create'));
    }

    #[Test]
    public function it_creates_a_user(): void
    {
        $status = $this->tester->execute([
            'email' => 'anna@example.test',
            'name' => 'Anna Berger',
            '--password' => 'ein-langes-passwort',
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(1, $this->users->countAll());
        self::assertNotNull($this->users->findByEmail('anna@example.test'));
    }

    #[Test]
    public function it_generates_and_prints_a_password_when_none_is_given(): void
    {
        // Der einzige Moment, in dem das Passwort im Klartext existiert -
        // danach gibt es nur noch den Hash.
        $this->tester->execute(['email' => 'anna@example.test', 'name' => 'Anna']);

        $output = $this->tester->getDisplay();

        self::assertStringContainsString('Passwort', $output);
        self::assertStringContainsString('nur in dieser Ausgabe', $output);
    }

    #[Test]
    public function it_does_not_print_a_password_that_was_supplied(): void
    {
        $this->tester->execute([
            'email' => 'anna@example.test',
            'name' => 'Anna',
            '--password' => 'ein-langes-passwort',
        ]);

        self::assertStringNotContainsString('ein-langes-passwort', $this->tester->getDisplay());
    }

    #[Test]
    public function it_grants_the_admin_role_on_request(): void
    {
        $this->tester->execute([
            'email' => 'anna@example.test',
            'name' => 'Anna',
            '--password' => 'ein-langes-passwort',
            '--admin' => true,
        ]);

        self::assertTrue($this->users->findByEmail('anna@example.test')?->hasRole(Role::ADMIN));
    }

    #[Test]
    public function it_creates_the_team_if_it_does_not_exist(): void
    {
        $this->tester->execute([
            'email' => 'anna@example.test',
            'name' => 'Anna',
            '--password' => 'ein-langes-passwort',
            '--team' => 'Vertrieb',
        ]);

        self::assertSame(1, $this->teams->countAll());
        self::assertSame('Vertrieb', $this->users->findByEmail('anna@example.test')?->team()?->name());
    }

    #[Test]
    public function it_reports_a_duplicate_address_as_a_failure(): void
    {
        $this->tester->execute([
            'email' => 'anna@example.test',
            'name' => 'Anna',
            '--password' => 'ein-langes-passwort',
        ]);

        $status = $this->tester->execute([
            'email' => 'anna@example.test',
            'name' => 'Anna Zwei',
            '--password' => 'ein-langes-passwort',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('bereits einen Benutzer', $this->tester->getDisplay());
        self::assertSame(1, $this->users->countAll());
    }

    #[Test]
    public function it_reports_a_short_password_as_a_failure(): void
    {
        $status = $this->tester->execute([
            'email' => 'anna@example.test',
            'name' => 'Anna',
            '--password' => 'kurz',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame(0, $this->users->countAll());
    }
}
