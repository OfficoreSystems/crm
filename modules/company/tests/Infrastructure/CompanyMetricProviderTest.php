<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Infrastructure;

use Crm\Company\Domain\Company;
use Crm\Company\Infrastructure\SharedKernel\CompanyMetricProvider;
use Crm\Company\Tests\Double\InMemoryCompanyRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompanyMetricProviderTest extends TestCase
{
    #[Test]
    public function it_reports_the_number_of_companies_with_the_biggest_industry(): void
    {
        $repository = new InMemoryCompanyRepository();
        $repository->save(Company::create('Nordwind', 'Logistik'));
        $repository->save(Company::create('Helios', 'Energie'));
        $repository->save(Company::create('Talwind', 'Energie'));

        $metric = iterator_to_array((new CompanyMetricProvider($repository))->getMetrics())[0];

        self::assertSame('3', $metric->value);
        self::assertSame('2 Branchen, groesste: Energie', $metric->description);
    }

    #[Test]
    public function without_any_industry_the_description_stays_empty(): void
    {
        $repository = new InMemoryCompanyRepository();
        $repository->save(Company::create('Ohne Branche'));

        $metric = iterator_to_array((new CompanyMetricProvider($repository))->getMetrics())[0];

        self::assertSame('1', $metric->value);
        self::assertNull($metric->description);
    }

    #[Test]
    public function an_empty_database_reports_zero(): void
    {
        $metric = iterator_to_array((new CompanyMetricProvider(new InMemoryCompanyRepository()))->getMetrics())[0];

        self::assertSame('0', $metric->value);
    }
}
