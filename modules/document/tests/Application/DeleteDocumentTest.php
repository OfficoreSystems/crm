<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Application;

use Crm\Document\Application\DeleteDocument;
use Crm\Document\Domain\Document;
use Crm\Document\Tests\Double\InMemoryDocumentRepository;
use Crm\Document\Tests\Double\InMemoryDocumentStorage;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeleteDocumentTest extends TestCase
{
    #[Test]
    public function it_removes_both_the_row_and_the_file(): void
    {
        [$delete, $documents, $storage] = $this->delete();
        $document = $this->given($documents, $storage);

        ($delete)($document);

        self::assertSame(0, $documents->countAll());
        self::assertSame([], $storage->files);
    }

    #[Test]
    public function a_file_that_is_already_gone_does_not_stop_the_deletion(): void
    {
        // Sonst bliebe die Datenbankzeile stehen, weil die Datei fehlt - und
        // der Benutzer sieht dauerhaft einen Eintrag, den er nicht loswird.
        [$delete, $documents, $storage] = $this->delete();
        $document = $this->given($documents, $storage);
        $storage->files = [];

        ($delete)($document);

        self::assertSame(0, $documents->countAll());
    }

    /**
     * @return array{0: DeleteDocument, 1: InMemoryDocumentRepository, 2: InMemoryDocumentStorage}
     */
    private function delete(): array
    {
        $documents = new InMemoryDocumentRepository();
        $storage = new InMemoryDocumentStorage();

        return [new DeleteDocument($documents, $storage), $documents, $storage];
    }

    private function given(InMemoryDocumentRepository $documents, InMemoryDocumentStorage $storage): Document
    {
        $document = Document::record(
            subject: new SubjectRef('contact', 'kontakt-1'),
            filename: 'Angebot.pdf',
            mimeType: 'application/pdf',
            size: 5,
            storageKey: 'contact/2026/08/abc',
        );

        $documents->save($document);
        $storage->write($document->storageKey(), 'Hallo');

        return $document;
    }
}
