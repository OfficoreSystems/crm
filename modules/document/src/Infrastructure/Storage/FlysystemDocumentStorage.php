<?php

declare(strict_types=1);

namespace Crm\Document\Infrastructure\Storage;

use Crm\Document\Domain\DocumentFileMissing;
use Crm\Document\Domain\DocumentStorageInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

/**
 * Die einzige Klasse im Modul, die Flysystem kennt.
 *
 * Alles darueber arbeitet gegen {@see DocumentStorageInterface} - vier
 * Methoden, die sich im Test durch ein Array ersetzen lassen. Ein Wechsel des
 * Speichers oder der Bibliothek beruehrt genau diese Datei.
 */
final readonly class FlysystemDocumentStorage implements DocumentStorageInterface
{
    public function __construct(
        private FilesystemOperator $filesystem,
    ) {
    }

    public function write(string $key, mixed $contents): void
    {
        if (\is_resource($contents)) {
            $this->filesystem->writeStream($key, $contents);

            return;
        }

        $this->filesystem->write($key, (string) $contents);
    }

    public function readStream(string $key): mixed
    {
        try {
            return $this->filesystem->readStream($key);
        } catch (FilesystemException) {
            // Eine Datenbankzeile ohne Datei ist kein Programmfehler, sondern
            // ein Zustand - abgebrochener Upload, zurueckgespieltes Backup.
            // Die Oberflaeche macht daraus eine 404.
            throw DocumentFileMissing::at($key);
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->filesystem->delete($key);
        } catch (FilesystemException) {
            // Absichtlich still: Loeschen ist idempotent gemeint. Eine bereits
            // fehlende Datei soll das Aufraeumen nicht aufhalten - sonst
            // bliebe die Datenbankzeile stehen, weil die Datei fehlt.
        }
    }

    public function has(string $key): bool
    {
        try {
            return $this->filesystem->fileExists($key);
        } catch (FilesystemException) {
            return false;
        }
    }
}
