<?php

declare(strict_types=1);

namespace Crm\User\Tests\Infrastructure\Security;

use Crm\User\Domain\Role;
use Crm\User\Domain\User;
use Crm\User\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecurityUserTest extends TestCase
{
    #[Test]
    public function it_copies_the_relevant_fields_from_the_domain_user(): void
    {
        $user = User::create('anna@example.test', 'Anna Berger', 'hash', [Role::ADMIN]);

        $securityUser = SecurityUser::fromDomain($user);

        self::assertSame((string) $user->id(), $securityUser->id());
        self::assertSame('anna@example.test', $securityUser->email());
        self::assertSame('Anna Berger', $securityUser->name());
        self::assertSame('hash', $securityUser->getPassword());
        self::assertSame($user->roles(), $securityUser->getRoles());
    }

    #[Test]
    public function the_email_is_the_login_identifier(): void
    {
        $securityUser = SecurityUser::fromDomain(User::create('anna@example.test', 'Anna', 'hash'));

        self::assertSame('anna@example.test', $securityUser->getUserIdentifier());
    }

    #[Test]
    public function the_password_can_be_replaced_after_a_rehash(): void
    {
        $securityUser = SecurityUser::fromDomain(User::create('anna@example.test', 'Anna', 'alt'));

        $securityUser->setPassword('neu');

        self::assertSame('neu', $securityUser->getPassword());
    }

    #[Test]
    public function erasing_credentials_does_nothing(): void
    {
        // Es liegt nie ein Klartext-Passwort in diesem Objekt. Der Test haelt
        // fest, dass die Methode den Hash nicht versehentlich loescht.
        $securityUser = SecurityUser::fromDomain(User::create('anna@example.test', 'Anna', 'hash'));

        $securityUser->eraseCredentials();

        self::assertSame('hash', $securityUser->getPassword());
    }
}
