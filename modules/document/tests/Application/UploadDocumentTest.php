<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Application;

use Crm\Document\Application\DocumentTooLarge;
use Crm\Document\Application\UploadDocument;
use Crm\Document\Application\UploadDocumentCommand;
use Crm\Document\Domain\UnresolvableSubject;
use Crm\Document\Tests\Double\InMemoryDocumentRepository;
use Crm\Document\Tests\Double\InMemoryDocumentStorage;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Zwei Speicher ohne gemeinsame Transaktion - hier entscheidet sich, welcher
 * Fehlerfall uebrig bleibt.
 */
final class UploadDocumentTest extends TestCase
{
    #[Test]
    public function it_stores_the_file_and_the_row(): void
    {
        [$upload, $documents, $storage] = $this->upload();

        $document = ($upload)($this->command());

        self::assertSame(1, $documents->countAll());
        self::assertSame('Hallo', $storage->files[$document->storageKey()] ?? null);
    }

    #[Test]
    public function the_storage_key_owes_nothing_to_the_filename(): void
    {
        // Sonst wuerde der zweite "Angebot.pdf" den ersten ueberschreiben.
        [$upload, , $storage] = $this->upload();

        ($upload)($this->command(filename: 'Angebot.pdf'));
        ($upload)($this->command(filename: 'Angebot.pdf'));

        self::assertCount(2, $storage->files);

        foreach (array_keys($storage->files) as $key) {
            self::assertStringNotContainsString('Angebot', $key);
        }
    }

    #[Test]
    public function a_failed_save_takes_the_file_with_it(): void
    {
        // DER Test dieser Datei. Ohne das Aufraeumen bliebe bei jedem
        // fehlgeschlagenen Upload eine bezahlte Datei liegen, die niemand mehr
        // findet - und die auch niemandem auffaellt, weil es keine Zeile dazu
        // gibt.
        [$upload, $documents, $storage] = $this->upload();
        $documents->failOnSave = new \RuntimeException('Datenbank weg');

        try {
            ($upload)($this->command());
            self::fail('Der Fehler haette durchgereicht werden muessen.');
        } catch (\RuntimeException $e) {
            self::assertSame('Datenbank weg', $e->getMessage());
        }

        self::assertSame([], $storage->files, 'Es darf keine verwaiste Datei zurueckbleiben.');
    }

    #[Test]
    public function a_file_over_the_limit_never_reaches_the_storage(): void
    {
        [$upload, $documents, $storage] = $this->upload(maxBytes: 10);

        $this->expectException(DocumentTooLarge::class);

        try {
            ($upload)($this->command(size: 11));
        } finally {
            self::assertSame([], $storage->files, 'Erst pruefen, dann schreiben.');
            self::assertSame(0, $documents->saveCalls);
        }
    }

    #[Test]
    public function a_subject_nobody_resolves_is_refused(): void
    {
        // Geprueft wird der Typ, nicht die ID: ein Tippfehler im Typ faellt
        // sonst erst auf, wenn jemand die Liste oeffnet und nichts findet.
        [$upload, , $storage] = $this->upload();

        $this->expectException(UnresolvableSubject::class);

        try {
            ($upload)($this->command(subject: new SubjectRef('rechnung', 'r-1')));
        } finally {
            self::assertSame([], $storage->files);
        }
    }

    #[Test]
    public function it_accepts_a_stream_without_reading_it_into_memory_first(): void
    {
        // Eine 40-MB-Datei als Zeichenkette kostet 40 MB Arbeitsspeicher -
        // mal Anzahl gleichzeitiger Uploads.
        [$upload, , $storage] = $this->upload();
        $stream = fopen('php://memory', 'r+b');
        \assert(\is_resource($stream));
        fwrite($stream, 'Aus einem Stream');
        rewind($stream);

        $document = ($upload)($this->command(contents: $stream));

        self::assertSame('Aus einem Stream', $storage->files[$document->storageKey()]);
    }

    /**
     * @return array{0: UploadDocument, 1: InMemoryDocumentRepository, 2: InMemoryDocumentStorage}
     */
    private function upload(int $maxBytes = 1024): array
    {
        $documents = new InMemoryDocumentRepository();
        $storage = new InMemoryDocumentStorage();

        return [
            new UploadDocument(
                $documents,
                $storage,
                new SubjectResolverRegistry([new FakeContactResolver()]),
                $maxBytes,
            ),
            $documents,
            $storage,
        ];
    }

    private function command(
        ?SubjectRef $subject = null,
        string $filename = 'Angebot.pdf',
        int $size = 5,
        mixed $contents = 'Hallo',
    ): UploadDocumentCommand {
        return new UploadDocumentCommand(
            subject: $subject ?? new SubjectRef('contact', 'kontakt-1'),
            filename: $filename,
            mimeType: 'application/pdf',
            size: $size,
            contents: $contents,
        );
    }
}

final class FakeContactResolver implements SubjectResolverInterface
{
    public function type(): string
    {
        return 'contact';
    }

    public function typeLabel(): string
    {
        return 'Kontakt';
    }

    public function resolve(array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            $found[$id] = new ResolvedSubject('contact', $id, 'Anna Andresen');
        }

        return $found;
    }

    public function search(string $query, int $limit = 10): array
    {
        return [];
    }
}
