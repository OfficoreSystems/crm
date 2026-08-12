<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Infrastructure;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Integrationstest gegen eine echte Postgres. Die Suche ist der Teil des
 * Moduls, der sich mit einem Test-Double nicht ehrlich pruefen laesst -
 * LIKE-Semantik, LOWER() und das Escaping von Wildcards passieren in der
 * Datenbank, nicht in PHP.
 */
final class DoctrineContactRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ContactRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ContactRepositoryInterface::class);

        // Jeder Test laeuft in einer Transaktion, die danach zurueckgerollt
        // wird - so bleibt die Testdatenbank zwischen den Tests unveraendert.
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();

        parent::tearDown();
    }

    #[Test]
    public function it_persists_and_finds_a_contact(): void
    {
        $companyId = Uuid::v7();
        $contact = Contact::create('Anna', 'Berger', 'anna@example.test', $companyId);
        $this->repository->save($contact);

        $this->entityManager->clear();
        $found = $this->repository->find($contact->id());

        self::assertNotNull($found);
        self::assertSame('Anna Berger', $found->fullName());
        self::assertSame('anna@example.test', $found->email());
        self::assertTrue($companyId->equals($found->companyId()));
    }

    #[Test]
    public function a_company_id_pointing_nowhere_is_stored_without_complaint(): void
    {
        // Es gibt keinen Fremdschluessel auf company_companies - und genau
        // das ist Absicht. Die Datenbank kann die Gueltigkeit ueber die
        // Modulgrenze hinweg nicht kennen.
        $contact = Contact::create('Anna', 'Berger', companyId: Uuid::v7());

        $this->repository->save($contact);
        $this->entityManager->clear();

        self::assertNotNull($this->repository->find($contact->id())?->companyId());
    }

    #[Test]
    public function it_finds_contacts_by_company_id(): void
    {
        $nordwind = Uuid::v7();
        $atlas = Uuid::v7();
        $this->repository->save(Contact::create('Anna', 'Berger', companyId: $nordwind));
        $this->repository->save(Contact::create('Deniz', 'Yilmaz', companyId: $nordwind));
        $this->repository->save(Contact::create('Clara', 'Dupont', companyId: $atlas));
        $this->repository->save(Contact::create('Erik', 'Lindqvist'));

        self::assertCount(2, $this->repository->findByCompanyIds([(string) $nordwind]));
        self::assertCount(3, $this->repository->findByCompanyIds([(string) $nordwind, (string) $atlas]));
        self::assertSame([], $this->repository->findByCompanyIds([]));
    }

    #[Test]
    public function it_counts_contacts_per_company(): void
    {
        $nordwind = Uuid::v7();
        $this->repository->save(Contact::create('Anna', 'Berger', companyId: $nordwind));
        $this->repository->save(Contact::create('Deniz', 'Yilmaz', companyId: $nordwind));
        $this->repository->save(Contact::create('Erik', 'Lindqvist'));

        self::assertSame(2, $this->repository->countByCompanyId((string) $nordwind));
        self::assertSame(0, $this->repository->countByCompanyId((string) Uuid::v7()));
        self::assertSame(0, $this->repository->countByCompanyId('keine-uuid'), 'Muell darf keine Ausnahme ausloesen');
    }

    #[Test]
    public function the_search_widens_to_the_given_companies(): void
    {
        // Der Kern der modulübergreifenden Suche: "Nordwind" steht nirgends
        // in dieser Tabelle. Der Aufrufer hat den Namen vorher ueber den
        // CompanyFinder zu IDs aufgeloest und reicht sie hier herein.
        $nordwind = Uuid::v7();
        $this->repository->save(Contact::create('Anna', 'Berger', companyId: $nordwind));
        $this->repository->save(Contact::create('Erik', 'Lindqvist'));

        self::assertCount(0, $this->repository->search('Nordwind'), 'ohne IDs kein Treffer');
        self::assertCount(1, $this->repository->search('Nordwind', [(string) $nordwind]));
    }

    #[Test]
    public function the_search_combines_own_fields_and_companies_with_or(): void
    {
        // ODER, nicht UND: wer nach einer Firma sucht, will deren Kontakte
        // sehen, auch wenn keiner von ihnen so heisst.
        $nordwind = Uuid::v7();
        $this->repository->save(Contact::create('Anna', 'Berger', companyId: $nordwind));
        $this->repository->save(Contact::create('Nordwind', 'Mustermann'));

        self::assertCount(2, $this->repository->search('Nordwind', [(string) $nordwind]));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->repository->find(Uuid::v7()));
    }

    #[Test]
    public function it_removes_a_contact(): void
    {
        $contact = Contact::create('Bogdan', 'Petrov');
        $this->repository->save($contact);

        $this->repository->remove($contact);

        self::assertNull($this->repository->find($contact->id()));
        self::assertSame(0, $this->repository->countAll());
    }

    #[Test]
    public function it_counts_all_contacts(): void
    {
        self::assertSame(0, $this->repository->countAll());

        $this->repository->save(Contact::create('Anna', 'Berger'));
        $this->repository->save(Contact::create('Bogdan', 'Petrov'));

        self::assertSame(2, $this->repository->countAll());
    }

    #[Test]
    public function an_empty_query_returns_everything_sorted_by_last_name(): void
    {
        $this->givenContacts();

        $names = array_map(
            static fn (Contact $c): string => $c->lastName(),
            $this->repository->search(''),
        );

        self::assertSame(['Berger', 'Nowak', 'Yilmaz'], $names);
    }

    #[Test]
    public function it_searches_its_own_three_fields(): void
    {
        $this->givenContacts();

        self::assertCount(1, $this->repository->search('Anna'), 'Vorname');
        self::assertCount(1, $this->repository->search('Yilmaz'), 'Nachname');
        self::assertCount(1, $this->repository->search('anna@example.test'), 'E-Mail');
    }

    #[Test]
    public function a_company_name_alone_finds_nothing(): void
    {
        // Firmennamen stehen nicht in dieser Tabelle - nur IDs. Das Repository
        // weiss nicht, was "Atlas Bau" ist, und das soll auch so bleiben.
        $this->givenContacts();

        self::assertCount(0, $this->repository->search('Atlas Bau'));
    }

    #[Test]
    public function the_search_is_case_insensitive(): void
    {
        $this->givenContacts();

        self::assertCount(1, $this->repository->search('bERgEr'));
    }

    #[Test]
    public function it_matches_partial_words(): void
    {
        $this->givenContacts();

        self::assertCount(1, $this->repository->search('owak'));
        self::assertCount(1, $this->repository->search('ilmaz'));
    }

    #[Test]
    public function it_escapes_like_wildcards_in_the_search_term(): void
    {
        // Ohne das Escaping in addcslashes() waere "%" eine Suche nach allem
        // und "_" ein Joker fuer ein beliebiges Zeichen. Beides wuerde
        // Treffer liefern, die niemand gesucht hat.
        $this->givenContacts();

        self::assertCount(0, $this->repository->search('%'), 'Prozent darf kein Platzhalter sein');
        self::assertCount(0, $this->repository->search('_erger'), 'Unterstrich darf kein Joker sein');
        self::assertCount(1, $this->repository->search('Berger'), 'Gegenprobe: normaler Treffer');
    }

    #[Test]
    public function whitespace_only_queries_count_as_empty(): void
    {
        $this->givenContacts();

        self::assertCount(3, $this->repository->search('   '));
    }

    #[Test]
    public function it_respects_the_limit(): void
    {
        $this->givenContacts();

        self::assertCount(2, $this->repository->search('', [], 2));
    }

    #[Test]
    public function a_limit_below_one_still_returns_a_row(): void
    {
        // max(1, $limit) im Repository - setMaxResults(0) wuerde sonst eine
        // leere Liste liefern und wie "keine Treffer" aussehen.
        $this->givenContacts();

        self::assertCount(1, $this->repository->search('', [], 0));
    }

    private function givenContacts(): void
    {
        $nordwind = Uuid::v7();
        $atlas = Uuid::v7();

        $this->repository->save(Contact::create('Anna', 'Berger', 'anna@example.test', $nordwind));
        $this->repository->save(Contact::create('Grzegorz', 'Nowak', 'g.nowak@atlas.test', $atlas));
        $this->repository->save(Contact::create('Deniz', 'Yilmaz', null, $atlas));
    }
}
