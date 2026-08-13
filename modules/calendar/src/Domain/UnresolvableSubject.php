<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

use Crm\SharedKernel\Localization\TranslatableText;

/**
 * Ein Termin soll an einem Typ haengen, den niemand aufloest.
 */
final class UnresolvableSubject extends \InvalidArgumentException
{
    private function __construct(
        string $message,
        public readonly TranslatableText $translatable,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $known
     */
    public static function ofType(string $type, array $known): self
    {
        $knownList = [] === $known ? 'none' : implode(', ', $known);

        return new self(
            sprintf('No module resolves the type "%s". Known types: %s.', $type, $knownList),
            TranslatableText::of('calendar.error.unresolvable_subject', [
                '%type%' => $type,
                '%known%' => $knownList,
            ]),
        );
    }
}