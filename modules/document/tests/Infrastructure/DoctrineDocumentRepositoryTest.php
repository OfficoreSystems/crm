<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Infrastructure;

use Crm\Document\Domain\Document;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Gegen die echte Datenbank, nicht gegen ein Double.
 *
 * Was hier geprueft wird, kann ein In-Memory-Repository gar nicht falsch
 * machen: Spaltentypen, der polymorphe Verweis als zwei Zeichenketten und die
 * Summe, die Postgres rechnet statt PHP.
 */
final class DoctrineDocumentRepositoryTest extends KernelTestCase
{
    private DocumentRepositoryInterface $documents;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->documents = static::getContainer()->get(DocumentRepositoryInterface::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();

        parent::tearDown();
    }

    private function purge(): void
    {
        $this->entityManager->getConnection()->executeStatement('DELETE FROM document_documents');
        $this->entityManager->clear();
    }

    #[Test]
    public function a_document_survives_a_round_trip(): void
    {
        $owner = Uuid::v7();
        $document = $this->given('Angebot.pdf', 'contact', 'kontakt-1', 2048, $owner);

        $this->entityManager->clear();
        $found = $this->documents->find($document->id());

        self::assertNotNull($found);
        self::assertSame('Angebot.pdf', $found->filename());
        self::assertSame(2048, $found->size());
        self::assertSame('application/pdf', $found->mimeType());
        self::assertTrue($owner->equals($found->ownerId()));
    }

    #[Test]
    public function the_polymorphic_reference_survives_a_round_trip(): void
    {
        $document = $this->given('Angebot.pdf', 'contact', 'kontakt-1');

        $this->entityManager->clear();
        $found = $this->documents->find($document->id());

        self::assertNotNull($found);
        self::assertSame('contact', $found->subject()->type);
        self::assertSame('kontakt-1', $found->subject()->id);
    }

    #[Test]
    public function the_same_id_under_a_different_type_is_a_different_subject(): void
    {
        // Ohne diese Trennung waere Kontakt 7 dasselbe wie Firma 7 - und
        // Dokumente tauchten am falschen Datensatz auf.
        $this->given('Kontakt.pdf', 'contact', 'gleiche-id');
        $this->given('Firma.pdf', 'company', 'gleiche-id');

        $forContact = $this->documents->findForSubject(new SubjectRef('contact', 'gleiche-id'));

        self::assertCount(1, $forContact);
        self::assertSame('Kontakt.pdf', $forContact[0]->filename());
    }

    #[Test]
    public function the_newest_document_comes_first(): void
    {
        $this->given('Alt.pdf', 'contact', 'kontakt-1', uploadedAt: new \DateTimeImmutable('-3 days'));
        $this->given('Neu.pdf', 'contact', 'kontakt-1', uploadedAt: new \DateTimeImmutable('-1 hour'));

        $found = $this->documents->findForSubject(new SubjectRef('contact', 'kontakt-1'));

        self::assertSame('Neu.pdf', $found[0]->filename());
        self::assertSame('Alt.pdf', $found[1]->filename());
    }

    #[Test]
    public function it_counts_per_subject(): void
    {
        $this->given('Eins.pdf', 'contact', 'kontakt-1');
        $this->given('Zwei.pdf', 'contact', 'kontakt-1');
        $this->given('Drei.pdf', 'contact', 'kontakt-2');

        self::assertSame(2, $this->documents->countForSubject(new SubjectRef('contact', 'kontakt-1')));
        self::assertSame(1, $this->documents->countForSubject(new SubjectRef('contact', 'kontakt-2')));
    }

    #[Test]
    public function the_total_size_is_summed_in_the_database(): void
    {
        $this->given('Eins.pdf', 'contact', 'kontakt-1', 1000);
        $this->given('Zwei.pdf', 'contact', 'kontakt-1', 2500);

        self::assertSame(3500, $this->documents->totalBytes());
    }

    #[Test]
    public function an_empty_table_sums_to_zero_and_not_to_null(): void
    {
        // SUM() ueber null Zeilen liefert NULL. Ohne COALESCE waere die
        // Kennzahl auf der Uebersicht dann leer statt "0 B".
        self::assertSame(0, $this->documents->totalBytes());
    }

    #[Test]
    public function it_removes_a_document(): void
    {
        $document = $this->given('Angebot.pdf', 'contact', 'kontakt-1');

        $this->documents->remove($document);

        self::assertNull($this->documents->find($document->id()));
        self::assertSame(0, $this->documents->countAll());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->documents->find(Uuid::v7()));
    }

    private function given(
        string $filename,
        string $subjectType,
        string $subjectId,
        int $size = 100,
        ?Uuid $ownerId = null,
        ?\DateTimeImmutable $uploadedAt = null,
    ): Document {
        $document = Document::record(
            subject: new SubjectRef($subjectType, $subjectId),
            filename: $filename,
            mimeType: 'application/pdf',
            size: $size,
            storageKey: $subjectType.'/2026/08/'.Uuid::v7()->toRfc4122(),
            ownerId: $ownerId,
            uploadedAt: $uploadedAt,
        );

        $this->documents->save($document);

        return $document;
    }
}
