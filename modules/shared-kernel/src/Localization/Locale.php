<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Localization;

/**
 * The languages this application speaks.
 *
 * A closed enumeration and not a free-form string: a column in which "de",
 * "de_DE", "German" and "" can sit next to each other will end up looking
 * exactly like that. When reading from the database {@see tryFrom()} catches the
 * rest.
 *
 * The value is also the Symfony locale code - keeping the two apart would be a
 * conversion without any return.
 */
enum Locale: string
{
    case ENGLISH = 'en';
    case GERMAN = 'de';

    /**
     * What applies when nobody has said otherwise.
     *
     * Has to match default_locale in config/packages/translation.yaml; a test
     * holds the two together.
     */
    public static function default(): self
    {
        return self::ENGLISH;
    }

    /**
     * The name of the language *in that language*.
     *
     * Not translated, and that is deliberate: whoever cannot currently read the
     * interface looks for "Deutsch" and not for "German".
     */
    public function label(): string
    {
        return match ($this) {
            self::ENGLISH => 'English',
            self::GERMAN => 'Deutsch',
        };
    }

    /**
     * From a stored value - or the default.
     *
     * Catches three cases at once: null (never chosen), garbage (written into
     * the database by hand) and a language that existed once and does not any
     * more.
     */
    public static function fromStringOrDefault(?string $value): self
    {
        return null === $value ? self::default() : self::tryFrom($value) ?? self::default();
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
