<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

/**
 * Ein Dokument soll an einem Typ haengen, den niemand aufloest.
 *
 * Geprueft wird der *Typ*, nicht die ID - wie bei Aktivitaeten. Ob der
 * Datensatz noch existiert, ist eine Frage von morgen, und ein geloeschter
 * Kontakt soll seine Dokumente nicht mitreissen.
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
