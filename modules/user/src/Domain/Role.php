<?php

declare(strict_types=1);

namespace Crm\User\Domain;

/**
 * Die Rollen, die das System kennt.
 *
 * Als Enum statt als lose Strings, damit ein Tippfehler beim Compiler
 * auffaellt und nicht erst dann, wenn jemand unbemerkt keine Rechte mehr hat.
 * Die Werte tragen das ROLE_-Praefix, weil Symfonys Security genau darauf
 * prueft.
 */
enum Role: string
{
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';

    /**
     * Rolle, die jeder angemeldete Benutzer hat.
     */
    public static function baseline(): self
    {
        return self::USER;
    }

    public function label(): string
    {
        return match ($this) {
            self::USER => 'Benutzer',
            self::ADMIN => 'Administrator',
        };
    }

    public static function tryFromName(string $value): ?self
    {
        return self::tryFrom(str_starts_with($value, 'ROLE_') ? $value : 'ROLE_'.strtoupper($value));
    }
}
