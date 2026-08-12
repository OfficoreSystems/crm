<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Security;

use Crm\SharedKernel\Security\NullUserProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class NullUserProviderTest extends TestCase
{
    #[Test]
    public function it_knows_nobody(): void
    {
        $this->expectException(UserNotFoundException::class);

        (new NullUserProvider())->loadUserByIdentifier('anna@example.test');
    }

    #[Test]
    public function it_cannot_refresh_anyone(): void
    {
        $this->expectException(UnsupportedUserException::class);

        (new NullUserProvider())->refreshUser(new InMemoryUser('anna@example.test', 'hash'));
    }

    #[Test]
    public function it_supports_no_user_class(): void
    {
        self::assertFalse((new NullUserProvider())->supportsClass(InMemoryUser::class));
    }
}
