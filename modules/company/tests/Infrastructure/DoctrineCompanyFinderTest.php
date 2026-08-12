<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Infrastructure;

use Crm\Company\Domain\Address;
use Crm\Company\Domain\Company;
use Crm\Company\Infrastructure\SharedKernel\DoctrineCompanyFinder;
use Crm\Company\Tests\Double\InMemoryCompanyRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineCompanyFinderTest extends TestCase
{
    private InMemoryCompanyRepository $companies;
    private DoctrineCompanyFinder $finder;

    protected function setUp(): void
    {
        $this->companies = new InMemoryCompanyRepository();
        $this->finder = new DoctrineCompanyFinder($this->companies);
    }

    #[Test]
    public function it_maps_a_company_to_a_summary(): void
    {
        $company = Company::create('Nordwind Logistik', 'Logistik', address: new Address(city: 'Hamburg'));
        $this->companies->save($company);

        $summary = $this->finder->find((string) $company->id());

        self::assertNotNull($summary);
        self::assertSame((string) $company->id(), $summary->id);
        self::assertSame('Nordwind Logistik', $summary->name);
        self::assertSame('Logistik', $summary->industry);
        self::assertSame('Hamburg', $summary->city);
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->finder->find((string) Uuid::v7()));
        self::assertFalse($this->finder->exists((string) Uuid::v7()));
    }

    #[Test]
    public function a_malformed_id_returns_null_instead_of_throwing(): void
    {
        // Aufrufer sind andere Module, die die ID skalar gespeichert haben.
        // Eine veraltete oder falsch getippte ID darf dort nichts ausloesen.
        self::assertNull($this->finder->find('keine-uuid'));
        self::assertFalse($this->finder->exists(''));
    }

    #[Test]
    public function exists_confirms_a_known_company(): void
    {
        $company = Company::create('Nordwind');
        $this->companies->save($company);

        self::assertTrue($this->finder->exists((string) $company->id()));
    }

    #[Test]
    public function find_many_skips_unknown_ids_and_indexes_by_id(): void
    {
        $nordwind = Company::create('Nordwind');
        $atlas = Company::create('Atlas Bau');
        $this->companies->save($nordwind);
        $this->companies->save($atlas);

        $found = $this->finder->findMany([
            (string) $nordwind->id(),
            (string) Uuid::v7(),
            (string) $atlas->id(),
            'kaputt',
        ]);

        self::assertCount(2, $found);
        self::assertSame('Nordwind', $found[(string) $nordwind->id()]->name);
        self::assertSame('Atlas Bau', $found[(string) $atlas->id()]->name);
    }

    #[Test]
    public function it_lists_all_companies_sorted_by_name(): void
    {
        $this->companies->save(Company::create('Nordwind'));
        $this->companies->save(Company::create('Atlas Bau'));

        $names = array_map(static fn ($s): string => $s->name, $this->finder->findAll());

        self::assertSame(['Atlas Bau', 'Nordwind'], $names);
    }
}
