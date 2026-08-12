<?php

declare(strict_types=1);

namespace Crm\User\Infrastructure\Security;

use Crm\User\Domain\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Laedt Benutzer fuer Symfonys Security aus dem Domain-Repository.
 *
 * @implements UserProviderInterface<SecurityUser>
 * @implements PasswordUpgraderInterface<SecurityUser>
 */
final readonly class DomainUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->findByEmail($identifier);

        if (null === $user) {
            throw new UserNotFoundException(sprintf('Kein Benutzer mit der Adresse "%s".', $identifier));
        }

        // Deaktivierte Konten sollen sich nicht anmelden koennen. Bewusst
        // dieselbe Fehlermeldung wie bei unbekannter Adresse: sonst verraet
        // die Anmeldemaske, welche Adressen existieren.
        if (!$user->isActive()) {
            throw new UserNotFoundException(sprintf('Kein Benutzer mit der Adresse "%s".', $identifier));
        }

        return SecurityUser::fromDomain($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Nicht unterstuetzt: "%s".', $user::class));
        }

        // Bei jedem Request neu laden: sonst behaelt eine laufende Sitzung
        // Rollen, die dem Benutzer inzwischen entzogen wurden.
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class || is_subclass_of($class, SecurityUser::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        $domainUser = $this->users->findByEmail($user->getUserIdentifier());

        if (null === $domainUser) {
            return;
        }

        $domainUser->changePasswordHash($newHashedPassword);
        $this->users->save($domainUser);

        $user->setPassword($newHashedPassword);
    }
}
