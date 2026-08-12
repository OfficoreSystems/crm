<?php

declare(strict_types=1);

namespace Crm\User\Infrastructure\Security;

use Crm\User\Domain\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Reicht den Domain-Port an Symfonys Hasher-Factory durch.
 *
 * Die Konfiguration (Algorithmus, Kosten) steht in security.yaml und ist
 * damit umstellbar, ohne eine Zeile Domaincode anzufassen.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $factory,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        return $this->factory->getPasswordHasher(SecurityUser::class)->hash($plainPassword);
    }
}
