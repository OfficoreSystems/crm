<?php

declare(strict_types=1);

namespace Crm\User\Infrastructure\Security;

use Crm\User\Domain\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Die Bruecke zwischen der Domain-Entity und Symfonys Security.
 *
 * Sie existiert, damit {@see User} die Symfony-Interfaces *nicht*
 * implementieren muss. Andernfalls haenge die Domain am Security-Paket, und
 * die Deptrac-Regel "Domain haengt an nichts" waere aufgeweicht.
 *
 * Der Preis ist diese Klasse. Der Gegenwert: die Domain laesst sich ohne
 * Framework testen, und ein Wechsel des Authentifizierungsmechanismus
 * beruehrt nur die Infrastructure-Schicht.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    private function __construct(
        private readonly string $id,
        private readonly string $email,
        private readonly string $name,
        /** @var list<string> */
        private readonly array $roles,
        private string $passwordHash,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self(
            (string) $user->id(),
            $user->email(),
            $user->name(),
            $user->roles(),
            $user->passwordHash(),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function setPassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function eraseCredentials(): void
    {
        // Es liegt kein Klartext-Passwort in diesem Objekt - nichts zu loeschen.
    }
}
