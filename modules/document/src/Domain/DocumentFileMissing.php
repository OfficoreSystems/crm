<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

/**
 * Zur Datenbankzeile gibt es keine Datei im Speicher.
 *
 * Sollte nicht vorkommen, kommt aber vor: ein abgebrochener Upload, ein
 * zurueckgespieltes Backup, ein geleerter Bucket. Eine eigene Ausnahme, damit
 * die Oberflaeche daraus eine verstaendliche 404 machen kann statt eines
 * Stacktrace aus der Speicherbibliothek.
 */
final class DocumentFileMissing extends \RuntimeException
{
    public static function at(string $key): self
    {
        return new self(sprintf('Zum Eintrag existiert keine Datei ("%s").', $key));
    }
}
