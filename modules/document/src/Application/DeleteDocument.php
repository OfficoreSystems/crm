<?php

declare(strict_types=1);

namespace Crm\Document\Application;

use Crm\Document\Domain\Document;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\Document\Domain\DocumentStorageInterface;

/**
 * Use-Case: ein Dokument entfernen.
 *
 * Umgekehrte Reihenfolge zum Upload, aus demselben Grund: erst die Zeile, dann
 * die Datei. Bricht es dazwischen ab, bleibt eine verwaiste Datei zurueck -
 * unschoen, aber niemand sieht einen Eintrag, der ins Leere fuehrt.
 */
final readonly class DeleteDocument
{
    public function __construct(
        private DocumentRepositoryInterface $documents,
        private DocumentStorageInterface $storage,
    ) {
    }

    public function __invoke(Document $document): void
    {
        $key = $document->storageKey();

        $this->documents->remove($document);
        $this->storage->delete($key);
    }
}
