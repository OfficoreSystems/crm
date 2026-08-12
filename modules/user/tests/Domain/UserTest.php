<?php

declare(strict_types=1);

namespace Crm\User\Tests\Domain;

use Crm\User\Domain\Role;
use Crm\User\Domain\Team;
use Crm\User\Domain\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class UserTest extends TestCase
{
    #[Test]
    public function it_normalises_the_email_address(): void
    {
        // Adressen sind case-insensitiv. Ohne Normalisierung koennte man sich
        // zweimal mit derselben Adresse registrieren.
        $user = User::create('  Anna.Berger@Example.TEST  ', 'Anna', 'hash');

        self::assertSame('anna.berger@example.test', $user->email());
    }

    #[Test]
    #[DataProvider('invalidEmails')]
    public function it_rejects_invalid_email_addresses(string $email): void
    {
        $this->expectException(\InvalidArgumentException::class);

        User::create($email, 'Anna', 'hash');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'leer' => [''];
        yield 'nur Leerzeichen' => ['   '];
        yield 'ohne At' => ['anna.example.test'];
        yield 'ohne Domain' => ['anna@'];
        yield 'mit Leerzeichen' => ['an na@example.test'];
    }

    #[Test]
    public function it_rejects_a_blank_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        User::create('anna@example.test', '  ', 'hash');
    }

    #[Test]
    public function it_rejects_a_blank_password_hash(): void
    {
        // Ein leerer Hash waere ein Konto, an dem jeder Vergleich scheitert -
        // oder schlimmer, je nach Hasher, eines das jeder oeffnen kann.
        $this->expectException(\InvalidArgumentException::class);

        User::create('anna@example.test', 'Anna', '   ');
    }

    #[Test]
    public function every_user_has_the_baseline_role(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');

        self::assertSame([Role::USER->value], $user->roles());
        self::assertTrue($user->hasRole(Role::USER));
        self::assertFalse($user->hasRole(Role::ADMIN));
    }

    #[Test]
    public function the_baseline_role_is_not_duplicated(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash', [Role::USER, Role::ADMIN]);

        self::assertSame([Role::USER->value, Role::ADMIN->value], array_values(array_unique($user->roles())));
        self::assertCount(2, $user->roles());
    }

    #[Test]
    public function an_admin_keeps_the_baseline_role(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash', [Role::ADMIN]);

        self::assertTrue($user->hasRole(Role::ADMIN));
        self::assertTrue($user->hasRole(Role::USER), 'ROLE_USER muss immer dabei sein.');
    }

    #[Test]
    public function it_starts_active(): void
    {
        self::assertTrue(User::create('anna@example.test', 'Anna', 'hash')->isActive());
    }

    #[Test]
    public function it_can_be_deactivated_and_reactivated(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');

        $user->deactivate();
        self::assertFalse($user->isActive());

        $user->activate();
        self::assertTrue($user->isActive());
    }

    #[Test]
    public function it_has_no_team_by_default(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');

        self::assertNull($user->team());
        self::assertNull($user->teamId());
    }

    #[Test]
    public function it_can_join_and_leave_a_team(): void
    {
        $team = Team::create('Vertrieb');
        $user = User::create('anna@example.test', 'Anna', 'hash');

        $user->joinTeam($team);
        self::assertSame($team, $user->team());
        self::assertTrue($team->id()->equals($user->teamId()));

        $user->joinTeam(null);
        self::assertNull($user->teamId());
    }

    #[Test]
    public function it_can_change_its_attributes(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');

        $user->changeEmail('ANNA.NEU@Example.test');
        $user->rename('  Anna Berger  ');
        $user->changePasswordHash('neuer-hash');
        $user->changeRoles([Role::ADMIN]);

        self::assertSame('anna.neu@example.test', $user->email());
        self::assertSame('Anna Berger', $user->name());
        self::assertSame('neuer-hash', $user->passwordHash());
        self::assertTrue($user->hasRole(Role::ADMIN));
    }

    #[Test]
    public function it_rejects_an_empty_new_password_hash(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');

        $this->expectException(\InvalidArgumentException::class);

        $user->changePasswordHash('');
    }

    #[Test]
    public function it_accepts_a_long_enough_password(): void
    {
        $this->expectNotToPerformAssertions();

        User::assertPasswordIsAcceptable(str_repeat('a', User::MINIMUM_PASSWORD_LENGTH));
    }

    #[Test]
    public function it_rejects_a_short_password(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        User::assertPasswordIsAcceptable(str_repeat('a', User::MINIMUM_PASSWORD_LENGTH - 1));
    }

    #[Test]
    public function it_assigns_a_sortable_uuid_and_keeps_the_timestamp(): void
    {
        $moment = new \DateTimeImmutable('2026-03-01 09:15:00');

        $user = User::create('anna@example.test', 'Anna', 'hash', createdAt: $moment);

        self::assertInstanceOf(UuidV7::class, $user->id());
        self::assertSame($moment, $user->createdAt());
    }
}
