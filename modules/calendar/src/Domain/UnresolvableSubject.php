<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

/**
 * Ein Termin soll an einem Typ haengen, den niemand aufloest.
 */
final class UnresolvableSubject extends \InvalidArgumentException
{
    /**
     * @param list<string> $known
     */
    public static function ofType(string $type, array $known): self
    {
        return new self(sprintf(
            'Kein Modul loest den Typ "%s" auf. Bekannt sind: %s.',
            $type,
            [] === $known ? 'keine' : implode(', ', $known),
        ));
    }
}
