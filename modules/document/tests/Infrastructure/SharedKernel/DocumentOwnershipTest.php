<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Infrastructure\SharedKernel;

use Crm\Document\Domain\Document;
use Crm\Document\Infrastructure\SharedKernel\DocumentOwnership;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DocumentOwnershipTest extends TestCase
{
    #[Test]
    public function it_answers_only_for_documents(): void
    {
        $ownership = new DocumentOwnership();

        self::assertSame('document', $ownership->module());
        self::assertTrue($ownership->supports($this->document()));
        self::assertFalse($ownership->supports(new \stdClass()));
    }

    #[Test]
    public function owner_and_team_do_not_get_swapped(): void
    {
        $owner = Uuid::v7();
        $team = Uuid::v7();

        $ownership = (new DocumentOwnership())->ownershipOf($this->document($owner, $team));

        self::assertSame($owner->toString(), $ownership->ownerId);
        self::assertSame($team->toString(), $ownership->teamId);
    }

    #[Test]
    public function it_declares_the_columns_the_visibility_filter_needs(): void
    {
        // Ohne diese Angaben wuerde der Filter Dokumente nicht einschraenken -
        // und eine hochgeladene Datei ist oft vertraulicher als der Datensatz,
        // an dem sie haengt.
        $columns = (new DocumentOwnership())->restrictedColumns();

        self::assertSame(Document::class, $columns->entityClass);
        self::assertSame('owner_id', $columns->ownerColumn);
        self::assertSame('owner_team_id', $columns->teamColumn);
    }

    private function document(?Uuid $ownerId = null, ?Uuid $ownerTeamId = null): Document
    {
        return Document::record(
            subject: new SubjectRef('contact', 'kontakt-1'),
            filename: 'Angebot.pdf',
            mimeType: 'application/pdf',
            size: 2048,
            storageKey: 'contact/2026/08/abc',
            ownerId: $ownerId,
            ownerTeamId: $ownerTeamId,
        );
    }
}
