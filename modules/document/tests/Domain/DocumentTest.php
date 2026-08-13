<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Domain;

use Crm\Document\Domain\Document;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DocumentTest extends TestCase
{
    #[Test]
    public function it_keeps_what_it_was_given(): void
    {
        $owner = Uuid::v7();
        $team = Uuid::v7();

        $document = Document::record(
            subject: new SubjectRef('contact', 'kontakt-1'),
            filename: 'Angebot.pdf',
            mimeType: 'application/pdf',
            size: 2048,
            storageKey: 'contact/2026/08/abc',
            ownerId: $owner,
            ownerTeamId: $team,
        );

        self::assertSame('contact', $document->subject()->type);
        self::assertSame('kontakt-1', $document->subject()->id);
        self::assertSame('Angebot.pdf', $document->filename());
        self::assertSame('application/pdf', $document->mimeType());
        self::assertSame(2048, $document->size());
        self::assertSame('contact/2026/08/abc', $document->storageKey());
        self::assertTrue($owner->equals($document->ownerId()));
        self::assertTrue($team->equals($document->ownerTeamId()));
    }

    #[Test]
    public function the_filename_goes_through_the_sanitiser(): void
    {
        // Die Domain verlaesst sich nicht darauf, dass der Controller
        // aufgeraeumt hat. Ein Konsolenbefehl oder ein Import kaeme sonst am
        // Schutz vorbei.
        $document = $this->document(filename: '../../etc/passwd');

        self::assertSame('passwd', $document->filename());
    }

    #[Test]
    public function renaming_does_not_move_the_file(): void
    {
        // Sonst muesste jede Umbenennung kopieren, und ein Abbruch dazwischen
        // liesse einen Eintrag ohne Datei zurueck.
        $document = $this->document();
        $key = $document->storageKey();

        $document->renameTo('Angebot final.pdf');

        self::assertSame('Angebot final.pdf', $document->filename());
        self::assertSame($key, $document->storageKey());
    }

    #[Test]
    public function an_empty_file_is_refused(): void
    {
        $this->expectExceptionMessage('ohne Inhalt');

        $this->document(size: 0);
    }

    #[Test]
    public function a_missing_storage_key_is_refused(): void
    {
        // Ein Eintrag ohne Schluessel waere fuer immer unauffindbar - und der
        // Fehler faellt erst beim ersten Download auf.
        $this->expectExceptionMessage('Speicherschluessel');

        $this->document(storageKey: '   ');
    }

    #[Test]
    public function an_unknown_content_type_becomes_the_generic_one(): void
    {
        // Ein leerer Content-Type-Header macht die Antwort beim Download
        // ungueltig. Der generische Typ fuehrt zum Speichern-Dialog, und das
        // ist das gewuenschte Verhalten.
        self::assertSame('application/octet-stream', $this->document(mimeType: '')->mimeType());
    }

    #[Test]
    public function without_an_owner_it_belongs_to_nobody(): void
    {
        $document = $this->document();

        self::assertNull($document->ownerId());
        self::assertNull($document->ownerTeamId());
    }

    private function document(
        string $filename = 'Angebot.pdf',
        string $mimeType = 'application/pdf',
        int $size = 2048,
        string $storageKey = 'contact/2026/08/abc',
    ): Document {
        return Document::record(
            subject: new SubjectRef('contact', 'kontakt-1'),
            filename: $filename,
            mimeType: $mimeType,
            size: $size,
            storageKey: $storageKey,
        );
    }
}
