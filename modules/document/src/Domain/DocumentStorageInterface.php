<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

/**
 * Der Objektspeicher, wie ihn dieses Modul braucht - vier Methoden.
 *
 * Ein eigenes Interface und nicht direkt Flysystem: die Domain und die
 * Anwendungsschicht sollen nicht an einer Fremdbibliothek haengen, und zum
 * Testen ist ein Array im Speicher genug. Die Umsetzung liegt in
 * Infrastructure und ist die einzige Stelle, die Flysystem kennt.
 */
interface DocumentStorageInterface
{
    /**
     * @param resource|string $contents
     */
    public function write(string $key, mixed $contents): void;

    /**
     * @return resource
     *
     * @throws DocumentFileMissing wenn zur Datenbankzeile keine Datei existiert
     */
    public function readStream(string $key): mixed;

    public function delete(string $key): void;

    public function has(string $key): bool;
}
