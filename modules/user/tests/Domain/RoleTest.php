<?php

declare(strict_types=1);

namespace Crm\User\Tests\Domain;

use Crm\User\Domain\Role;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    #[Test]
    public function every_role_carries_the_symfony_prefix(): void
    {
        // Symfonys Security prueft auf genau dieses Praefix. Ohne waere die
        // Rolle wirkungslos, ohne dass irgendwo etwas fehlschlaegt.
        foreach (Role::cases() as $role) {
            self::assertStringStartsWith('ROLE_', $role->value);
        }
    }

    #[Test]
    public function every_role_has_a_label(): void
    {
        foreach (Role::cases() as $role) {
            self::assertNotSame('', $role->label());
        }
    }

    #[Test]
    public function the_baseline_role_is_the_plain_user(): void
    {
        self::assertSame(Role::USER, Role::baseline());
    }

    #[Test]
    public function it_resolves_names_with_and_without_prefix(): void
    {
        self::assertSame(Role::ADMIN, Role::tryFromName('ROLE_ADMIN'));
        self::assertSame(Role::ADMIN, Role::tryFromName('admin'));
        self::assertSame(Role::USER, Role::tryFromName('User'));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_name(): void
    {
        self::assertNull(Role::tryFromName('superuser'));
        self::assertNull(Role::tryFromName('ROLE_SUPERUSER'));
    }
}
