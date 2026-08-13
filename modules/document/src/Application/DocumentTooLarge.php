<?php

declare(strict_types=1);

namespace Crm\Document\Application;

use Crm\Document\Domain\FileSize;

/**
 * Die Datei ueberschreitet das serverseitige Limit.
 *
 * Serverseitig, weil ein `max`-Attribut im Formular niemanden aufhaelt, der
 * die Anfrage selbst baut - und weil PHPs upload_max_filesize eine weisse
 * Seite erzeugt statt einer Meldung.
 */
final class DocumentTooLarge extends \RuntimeException
{
    public static function of(int $size, int $maxBytes): self
    {
        return new self(sprintf(
            'Die Datei ist %s gross, erlaubt sind %s.',
            FileSize::humanize($size),
            FileSize::humanize($maxBytes),
        ));
    }
}
