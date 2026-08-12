<?php

declare(strict_types=1);

namespace Crm\Activity\Domain;

final class UnresolvableSubject extends \DomainException
{
    /**
     * @param list<string> $supported
     */
    public static function ofType(string $type, array $supported): self
    {
        return new self(sprintf(
            'Fuer den Subjekt-Typ "%s" ist kein Resolver registriert. Verfuegbar: %s.',
            $type,
            [] === $supported ? 'keiner' : implode(', ', $supported),
        ));
    }
}
