<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Localization;

/**
 * Die Sprachen, die diese Anwendung spricht.
 *
 * Eine geschlossene Aufzaehlung und keine freie Zeichenkette: eine Spalte, in
 * der "de", "de_DE", "German" und "" nebeneinander stehen koennen, wird genau
 * so aussehen. Beim Lesen aus der Datenbank faengt {@see tryFrom()} den Rest
 * ab.
 *
 * Der Wert ist zugleich der Symfony-Locale-Code - beide auseinanderzuhalten
 * waere eine Umrechnung ohne Gegenwert.
 */
enum Locale: string
{
    case ENGLISH = 'en';
    case GERMAN = 'de';

    /**
     * Was gilt, wenn niemand etwas anderes gesagt hat.
     *
     * Muss zu default_locale in config/packages/translation.yaml passen; ein
     * Test haelt beides zusammen.
     */
    public static function default(): self
    {
        return self::ENGLISH;
    }

    /**
     * Der Name der Sprache *in dieser Sprache*.
     *
     * Nicht uebersetzt, und das ist Absicht: wer die Oberflaeche gerade nicht
     * versteht, sucht nach "Deutsch" und nicht nach "German".
     */
    public function label(): string
    {
        return match ($this) {
            self::ENGLISH => 'English',
            self::GERMAN => 'Deutsch',
        };
    }

    /**
     * Aus einem gespeicherten Wert - oder die Vorgabe.
     *
     * Faengt drei Faelle auf einmal ab: null (nie gewaehlt), Muell (von Hand
     * in die Datenbank geschrieben) und eine Sprache, die es einmal gab und
     * heute nicht mehr.
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
