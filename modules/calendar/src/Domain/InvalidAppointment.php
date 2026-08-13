<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

use Crm\SharedKernel\Localization\TranslatableText;

/**
 * Eine Eingabe, aus der sich kein Termin machen laesst.
 *
 * Eine eigene Klasse statt \InvalidArgumentException, damit die Oberflaeche
 * genau diese Faelle auffangen kann - und nicht versehentlich auch einen
 * Programmierfehler, der zufaellig dieselbe Ausnahme wirft.
 *
 * Traegt zwei Formulierungen: eine feste englische fuers Log und einen
 * Uebersetzungsschluessel fuer den Benutzer. Ein Log in der Sprache des gerade
 * angemeldeten Benutzers waere beim Suchen nach Fehlern nicht hilfreich.
 */
final class InvalidAppointment extends \InvalidArgumentException
{
    private function __construct(
        string $message,
        public readonly TranslatableText $translatable,
    ) {
        parent::__construct($message);
    }

    public static function endsBeforeItStarts(): self
    {
        return new self(
            'An appointment cannot end before it begins.',
            TranslatableText::of('calendar.error.end_before_start'),
        );
    }

    public static function withoutTitle(): self
    {
        return new self(
            'An appointment without a title cannot be found in any calendar.',
            TranslatableText::of('calendar.error.no_title'),
        );
    }

    public static function shorterThanADay(): self
    {
        return new self(
            'An all-day appointment lasts at least one day.',
            TranslatableText::of('calendar.error.all_day_minimum'),
        );
    }

    public static function withoutStart(): self
    {
        return new self(
            'Without a start there is no appointment to add.',
            TranslatableText::of('calendar.error.no_start'),
        );
    }

    public static function unparsable(string $value): self
    {
        return new self(
            sprintf('Cannot make a point in time out of "%s".', $value),
            TranslatableText::of('calendar.error.unparsable', ['%value%' => $value]),
        );
    }
}
