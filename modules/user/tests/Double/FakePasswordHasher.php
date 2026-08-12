<?php

declare(strict_types=1);

namespace Crm\User\Tests\Double;

use Crm\User\Domain\PasswordHasherInterface;

/**
 * Ersetzt echtes Hashing in Unit-Tests.
 *
 * Argon2 pro Testfall zu rechnen kostet mehr Zeit als der ganze Rest der
 * Suite. Der Praefix macht in Assertions sichtbar, dass ueberhaupt gehasht
 * wurde - und dass niemand versehentlich Klartext speichert.
 */
final class FakePasswordHasher implements PasswordHasherInterface
{
    public const PREFIX = 'hashed:';

    public function hash(string $plainPassword): string
    {
        return self::PREFIX.strrev($plainPassword);
    }
}
