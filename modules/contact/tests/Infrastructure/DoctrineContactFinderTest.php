<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Infrastructure;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Infrastructure\SharedKernel\DoctrineContactFinder;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Was andere Module vom contact-Modul sehen: nur ContactSummary, nie die
 * Entity.
 */
final class DoctrineContactFinderTest extends TestCase
{
    private InMemoryContactRepository $contacts;
    private DoctrineContactFinder $finder;

    protected function setUp(): void
    {
        $this->contacts = new InMemoryContactRepository();
        $this->finder = new DoctrineContactFinder($this->contacts);
    }

    #[Test]
    public function it_maps_a_contact_to_a_summary(): void
    {
        $companyId = Uuid::v7();
        $contact = Contact::create('Anna', 'Berger', 'anna@example.test', $companyId);
        $this->contacts->save($contact);

        $summary = $this->finder->find((string) $contact->id());

        self::assertNotNull($summary);
        self::assertSame('Anna Berger', $summary->fullName);
        self::assertSame('anna@example.test', $summary->email);
        self::assertSame((string) $companyId, $summary->companyId);
    }

    #[Test]
    public function a_malformed_id_returns_null_instead_of_throwing(): void
    {
        self::assertNull($this->finder->find('keine-uuid'));
        self::assertFalse($this->finder->exists(''));
    }

    #[Test]
    public function it_confirms_a_known_contact(): void
    {
        $this->contacts->save($contact = Contact::create('Anna', 'Berger'));

        self::assertTrue($this->finder->exists((string) $contact->id()));
        self::assertFalse($this->finder->exists((string) Uuid::v7()));
    }

    #[Test]
    public function find_many_skips_unknown_ids_and_indexes_by_id(): void
    {
        $this->contacts->save($anna = Contact::create('Anna', 'Berger'));
        $this->contacts->save($erik = Contact::create('Erik', 'Lindqvist'));

        $found = $this->finder->findMany([(string) $anna->id(), (string) Uuid::v7(), (string) $erik->id()]);

        self::assertCount(2, $found);
        self::assertSame('Anna Berger', $found[(string) $anna->id()]->fullName);
    }

    #[Test]
    public function it_searches_by_name(): void
    {
        $this->contacts->save(Contact::create('Anna', 'Berger'));
        $this->contacts->save(Contact::create('Erik', 'Lindqvist'));

        self::assertCount(1, $this->finder->searchByName('Berger'));
        self::assertCount(0, $this->finder->searchByName('Gibtsnicht'));
    }

    #[Test]
    public function an_empty_query_returns_nothing_rather_than_everything(): void
    {
        // Aufrufer nutzen das, um einen Suchbegriff zu IDs aufzuloesen. Ein
        // leerer Begriff darf dabei nicht die ganze Datenbank zurueckgeben.
        $this->contacts->save(Contact::create('Anna', 'Berger'));

        self::assertSame([], $this->finder->searchByName('   '));
    }
}
