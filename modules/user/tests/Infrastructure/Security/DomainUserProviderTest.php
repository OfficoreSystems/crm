<?php

declare(strict_types=1);

namespace Crm\User\Tests\Infrastructure\Security;

use Crm\User\Domain\Role;
use Crm\User\Domain\User;
use Crm\User\Infrastructure\Security\DomainUserProvider;
use Crm\User\Infrastructure\Security\SecurityUser;
use Crm\User\Tests\Double\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class DomainUserProviderTest extends TestCase
{
    private InMemoryUserRepository $users;
    private DomainUserProvider $provider;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->provider = new DomainUserProvider($this->users);
    }

    #[Test]
    public function it_loads_an_active_user(): void
    {
        $this->users->save(User::create('anna@example.test', 'Anna', 'hash', [Role::ADMIN]));

        $securityUser = $this->provider->loadUserByIdentifier('anna@example.test');

        self::assertInstanceOf(SecurityUser::class, $securityUser);
        self::assertSame('anna@example.test', $securityUser->getUserIdentifier());
        self::assertSame('hash', $securityUser->getPassword());
        self::assertContains(Role::ADMIN->value, $securityUser->getRoles());
    }

    #[Test]
    public function it_rejects_an_unknown_address(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifier('niemand@example.test');
    }

    #[Test]
    public function a_deactivated_user_cannot_be_loaded(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');
        $user->deactivate();
        $this->users->save($user);

        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifier('anna@example.test');
    }

    #[Test]
    public function a_deactivated_account_is_indistinguishable_from_an_unknown_one(): void
    {
        // Absicht: eine eigene Meldung fuer "deaktiviert" wuerde der
        // Anmeldemaske verraten, welche Adressen existieren.
        $user = User::create('anna@example.test', 'Anna', 'hash');
        $user->deactivate();
        $this->users->save($user);

        $deactivated = $this->messageFor('anna@example.test');
        $unknown = $this->messageFor('niemand@example.test');

        self::assertSame(
            str_replace('anna@example.test', 'X', $deactivated),
            str_replace('niemand@example.test', 'X', $unknown),
        );
    }

    #[Test]
    public function refreshing_reloads_from_the_repository(): void
    {
        // Wichtig, damit entzogene Rollen sofort wirken und nicht erst nach
        // dem naechsten Anmelden.
        $user = User::create('anna@example.test', 'Anna', 'hash');
        $this->users->save($user);

        $stale = $this->provider->loadUserByIdentifier('anna@example.test');
        $user->changeRoles([Role::ADMIN]);
        $this->users->save($user);

        $fresh = $this->provider->refreshUser($stale);

        self::assertNotContains(Role::ADMIN->value, $stale->getRoles());
        self::assertContains(Role::ADMIN->value, $fresh->getRoles());
    }

    #[Test]
    public function refreshing_rejects_a_foreign_user_class(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->provider->refreshUser(new InMemoryUser('anna@example.test', 'hash'));
    }

    #[Test]
    public function it_supports_only_its_own_user_class(): void
    {
        self::assertTrue($this->provider->supportsClass(SecurityUser::class));
        self::assertFalse($this->provider->supportsClass(InMemoryUser::class));
    }

    #[Test]
    public function it_persists_an_upgraded_password(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'alter-hash');
        $this->users->save($user);
        $securityUser = $this->provider->loadUserByIdentifier('anna@example.test');
        self::assertInstanceOf(SecurityUser::class, $securityUser);

        $this->provider->upgradePassword($securityUser, 'neuer-hash');

        self::assertSame('neuer-hash', $this->users->findByEmail('anna@example.test')?->passwordHash());
        self::assertSame('neuer-hash', $securityUser->getPassword());
    }

    #[Test]
    public function upgrading_ignores_a_foreign_user_class(): void
    {
        $this->expectNotToPerformAssertions();

        $this->provider->upgradePassword(new InMemoryUser('anna@example.test', 'hash'), 'neuer-hash');
    }

    #[Test]
    public function upgrading_ignores_a_user_that_has_since_been_deleted(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'alter-hash');
        $this->users->save($user);
        $securityUser = $this->provider->loadUserByIdentifier('anna@example.test');
        self::assertInstanceOf(SecurityUser::class, $securityUser);
        $this->users->remove($user);

        $this->provider->upgradePassword($securityUser, 'neuer-hash');

        self::assertSame(0, $this->users->countAll());
    }

    private function messageFor(string $identifier): string
    {
        try {
            $this->provider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException $e) {
            return $e->getMessage();
        }

        self::fail('Es wurde keine UserNotFoundException geworfen.');
    }
}
