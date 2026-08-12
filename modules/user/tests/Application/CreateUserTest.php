<?php

declare(strict_types=1);

namespace Crm\User\Tests\Application;

use Crm\User\Application\CreateUser;
use Crm\User\Application\CreateUserCommand;
use Crm\User\Domain\EmailAlreadyInUse;
use Crm\User\Domain\Role;
use Crm\User\Domain\Team;
use Crm\User\Domain\TeamNotFound;
use Crm\User\Tests\Double\FakePasswordHasher;
use Crm\User\Tests\Double\InMemoryTeamRepository;
use Crm\User\Tests\Double\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateUserTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryTeamRepository $teams;
    private CreateUser $createUser;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->teams = new InMemoryTeamRepository();
        $this->createUser = new CreateUser($this->users, $this->teams, new FakePasswordHasher());
    }

    #[Test]
    public function it_creates_and_stores_a_user(): void
    {
        $user = ($this->createUser)(new CreateUserCommand(
            'anna@example.test',
            'Anna Berger',
            'ein-langes-passwort',
        ));

        self::assertSame(1, $this->users->countAll());
        self::assertSame($user, $this->users->findByEmail('anna@example.test'));
    }

    #[Test]
    public function it_never_stores_the_plain_password(): void
    {
        $user = ($this->createUser)(new CreateUserCommand(
            'anna@example.test',
            'Anna',
            'ein-langes-passwort',
        ));

        self::assertStringStartsWith(FakePasswordHasher::PREFIX, $user->passwordHash());
        self::assertStringNotContainsString('ein-langes-passwort', $user->passwordHash());
    }

    #[Test]
    public function it_assigns_the_requested_roles(): void
    {
        $user = ($this->createUser)(new CreateUserCommand(
            'anna@example.test',
            'Anna',
            'ein-langes-passwort',
            [Role::ADMIN],
        ));

        self::assertTrue($user->hasRole(Role::ADMIN));
    }

    #[Test]
    public function it_puts_the_user_into_the_given_team(): void
    {
        $team = Team::create('Vertrieb');
        $this->teams->save($team);

        $user = ($this->createUser)(new CreateUserCommand(
            'anna@example.test',
            'Anna',
            'ein-langes-passwort',
            teamId: $team->id(),
        ));

        self::assertSame($team, $user->team());
    }

    #[Test]
    public function it_rejects_an_unknown_team(): void
    {
        $this->expectException(TeamNotFound::class);

        ($this->createUser)(new CreateUserCommand(
            'anna@example.test',
            'Anna',
            'ein-langes-passwort',
            teamId: Uuid::v7(),
        ));
    }

    #[Test]
    public function it_rejects_a_duplicate_email(): void
    {
        ($this->createUser)(new CreateUserCommand('anna@example.test', 'Anna', 'ein-langes-passwort'));

        $this->expectException(EmailAlreadyInUse::class);

        ($this->createUser)(new CreateUserCommand('anna@example.test', 'Anna Zwei', 'ein-langes-passwort'));
    }

    #[Test]
    public function the_duplicate_check_uses_the_normalised_address(): void
    {
        // Der eigentliche Grund, warum erst konstruiert und dann geprueft
        // wird: " Anna@Example.TEST " und "anna@example.test" sind dieselbe
        // Adresse, und ein naiver Vergleich haette das durchgelassen.
        ($this->createUser)(new CreateUserCommand('anna@example.test', 'Anna', 'ein-langes-passwort'));

        $this->expectException(EmailAlreadyInUse::class);

        ($this->createUser)(new CreateUserCommand('  Anna@Example.TEST  ', 'Anna Zwei', 'ein-langes-passwort'));
    }

    #[Test]
    public function it_rejects_a_short_password_before_touching_the_repository(): void
    {
        try {
            ($this->createUser)(new CreateUserCommand('anna@example.test', 'Anna', 'kurz'));
            self::fail('Ein zu kurzes Passwort haette abgelehnt werden muessen.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $this->users->countAll());
        }
    }

    #[Test]
    public function it_stores_nothing_when_the_email_is_invalid(): void
    {
        try {
            ($this->createUser)(new CreateUserCommand('keine-adresse', 'Anna', 'ein-langes-passwort'));
            self::fail('Eine ungueltige Adresse haette abgelehnt werden muessen.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $this->users->countAll());
        }
    }
}
