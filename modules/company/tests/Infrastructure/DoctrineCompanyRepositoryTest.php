<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Infrastructure;

use Crm\Company\Domain\Address;
use Crm\Company\Domain\Company;
use Crm\Company\Domain\CompanyRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineCompanyRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CompanyRepositoryInterface $companies;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->companies = $container->get(CompanyRepositoryInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();

        parent::tearDown();
    }

    #[Test]
    public function the_embedded_address_survives_a_round_trip(): void
    {
        // Das Embeddable landet als address_-Spalten in derselben Tabelle.
        // Der Test faengt ab, dass beim Hydrieren aus null ein leerer String
        // wird oder umgekehrt.
        $company = Company::create('Nordwind', address: new Address('Am Hafen 12', '20457', 'Hamburg', 'DE'));
        $this->companies->save($company);

        $this->entityManager->clear();
        $found = $this->companies->find($company->id());

        self::assertNotNull($found);
        self::assertSame('Am Hafen 12', $found->address()->street);
        self::assertSame('20457', $found->address()->postalCode);
        self::assertSame('Hamburg', $found->address()->city);
        self::assertSame('DE', $found->address()->country);
    }

    #[Test]
    public function an_empty_address_stays_empty_after_a_round_trip(): void
    {
        $company = Company::create('Nordwind');
        $this->companies->save($company);

        $this->entityManager->clear();

        self::assertTrue($this->companies->find($company->id())?->address()->isEmpty());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->companies->find(Uuid::v7()));
    }

    #[Test]
    public function it_finds_a_company_by_name(): void
    {
        $this->companies->save(Company::create('Nordwind Logistik'));

        self::assertNotNull($this->companies->findByName('Nordwind Logistik'));
        self::assertNotNull($this->companies->findByName('  Nordwind Logistik  '));
        self::assertNull($this->companies->findByName('Gibt es nicht'));
    }

    #[Test]
    public function it_removes_a_company(): void
    {
        $company = Company::create('Nordwind');
        $this->companies->save($company);

        $this->companies->remove($company);

        self::assertSame(0, $this->companies->countAll());
    }

    #[Test]
    public function it_searches_name_industry_and_city(): void
    {
        $this->givenCompanies();

        self::assertCount(1, $this->companies->search('Nordwind'), 'Name');
        self::assertCount(2, $this->companies->search('Energie'), 'Branche');
        self::assertCount(1, $this->companies->search('Hamburg'), 'Ort aus dem Embeddable');
    }

    #[Test]
    public function the_search_is_case_insensitive_and_escapes_wildcards(): void
    {
        $this->givenCompanies();

        self::assertCount(1, $this->companies->search('nOrDwInD'));
        self::assertCount(0, $this->companies->search('%'));
        self::assertCount(0, $this->companies->search('_ordwind'));
    }

    #[Test]
    public function the_search_is_sorted_by_name_and_respects_the_limit(): void
    {
        $this->givenCompanies();

        $names = array_map(static fn (Company $c): string => $c->name(), $this->companies->search(''));
        self::assertSame(['Atlas Bau', 'Helios Gruppe', 'Nordwind Logistik', 'Talwind Energie'], $names);

        self::assertCount(2, $this->companies->search('', 2));
        self::assertCount(1, $this->companies->search('', 0), 'max(1, limit) verhindert eine leere Liste');
    }

    #[Test]
    public function it_counts_companies_per_industry_in_the_database(): void
    {
        $this->givenCompanies();

        $counts = $this->companies->countByIndustry();

        self::assertSame(['Energie' => 2, 'Bauwesen' => 1, 'Logistik' => 1], $counts);
    }

    #[Test]
    public function companies_without_an_industry_are_left_out_of_the_grouping(): void
    {
        $this->companies->save(Company::create('Ohne Branche'));
        $this->companies->save(Company::create('Mit Branche', 'Logistik'));

        self::assertSame(['Logistik' => 1], $this->companies->countByIndustry());
    }

    private function givenCompanies(): void
    {
        $this->companies->save(Company::create('Nordwind Logistik', 'Logistik', address: new Address(city: 'Hamburg')));
        $this->companies->save(Company::create('Helios Gruppe', 'Energie', address: new Address(city: 'Berlin')));
        $this->companies->save(Company::create('Atlas Bau', 'Bauwesen', address: new Address(city: 'Muenchen')));
        $this->companies->save(Company::create('Talwind Energie', 'Energie', address: new Address(city: 'Basel')));
    }
}
