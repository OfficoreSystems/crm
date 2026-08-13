<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Default user source: knows nobody.
 *
 * It exists because of a Symfony peculiarity: `security.firewalls` is a
 * prototyped node and must come in full from *one* configuration file. A module
 * therefore cannot extend the firewall through prependExtension() - the
 * container build aborts with "You are not allowed to define new elements for
 * path security.firewalls".
 *
 * So the core defines the firewall once and points at the fixed service ID
 * `crm.security.user_provider`. This class is the default behind it; the user
 * module overrides the alias with its own implementation. The core thus still
 * names no module, and without the user module the application starts - it is
 * only that nobody can sign in.
 *
 * @implements UserProviderInterface<UserInterface>
 */
final class NullUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('No module is installed that provides users.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException('No module is installed that provides users.');
    }

    public function supportsClass(string $class): bool
    {
        return false;
    }
}
