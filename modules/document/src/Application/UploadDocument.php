<?php

declare(strict_types=1);

namespace Crm\Document\Application;

use Crm\Document\Domain\Document;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\Document\Domain\DocumentStorageInterface;
use Crm\Document\Domain\StorageKey;
use Crm\Document\Domain\UnresolvableSubject;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;

/**
 * Use-Case: eine Datei an einen Datensatz haengen.
 *
 * Zwei Speicher, die zusammenpassen muessen - der Objektspeicher und die
 * Datenbank. Es gibt keine gemeinsame Transaktion, also entscheidet die
 * Reihenfolge, welcher Fehlerfall uebrig bleibt:
 *
 *   erst Datei, dann Zeile  -> im schlimmsten Fall eine verwaiste Datei
 *   erst Zeile, dann Datei  -> im schlimmsten Fall ein Eintrag ohne Datei
 *
 * Das Erste ist harmlos und aufraeumbar, das Zweite zeigt dem Benutzer ein
 * Dokument, das beim Klick nicht existiert. Deshalb diese Reihenfolge - und
 * deshalb wird die Datei wieder geloescht, wenn das Speichern der Zeile
 * scheitert.
 */
final readonly class UploadDocument
{
    public function __construct(
        private DocumentRepositoryInterface $documents,
        private DocumentStorageInterface $storage,
        private SubjectResolverRegistry $subjects,
        private int $maxBytes,
    ) {
    }

    public function __invoke(UploadDocumentCommand $command): Document
    {
        if (!$this->subjects->supports($command->subject->type)) {
            throw UnresolvableSubject::ofType(
                $command->subject->type,
                array_keys($this->subjects->supportedTypes()),
            );
        }

        if ($command->size > $this->maxBytes) {
            throw DocumentTooLarge::of($command->size, $this->maxBytes);
        }

        $key = StorageKey::for($command->subject->type);

        $this->storage->write($key, $command->contents);

        try {
            $document = Document::record(
                subject: $command->subject,
                filename: $command->filename,
                mimeType: $command->mimeType,
                size: $command->size,
                storageKey: $key,
                ownerId: $command->ownerId,
                ownerTeamId: $command->ownerTeamId,
            );

            $this->documents->save($document);
        } catch (\Throwable $e) {
            // Ohne dieses Aufraeumen bliebe bei jedem fehlgeschlagenen Upload
            // eine bezahlte Datei liegen, die niemand mehr findet.
            $this->storage->delete($key);

            throw $e;
        }

        return $document;
    }
}
