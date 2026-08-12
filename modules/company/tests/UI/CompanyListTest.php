<?php

declare(strict_types=1);

namespace Crm\Company\Tests\UI;

use Crm\Company\Domain\Company;
use Crm\Company\Tests\Double\InMemoryCompanyRepository;
use Crm\Company\UI\Component\CompanyList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompanyListTest extends TestCase
{
    #[Test]
    public function it_lists_everything_without_a_query(): void
    {
        $component = $this->componentWith(3);

        self::assertCount(3, $component->getCompanies());
        self::assertSame(3, $component->getTotal());
        self::assertFalse($component->isFiltered());
    }

    #[Test]
    public function it_narrows_the_list_down_to_the_query(): void
    {
        $component = $this->componentWith(3);
        $component->query = 'Firma2';

        self::assertCount(1, $component->getCompanies());
        self::assertSame(3, $component->getTotal());
        self::assertTrue($component->isFiltered());
    }

    #[Test]
    public function a_whitespace_query_does_not_count_as_filtered(): void
    {
        $component = $this->componentWith(1);
        $component->query = '  ';

        self::assertFalse($component->isFiltered());
    }

    #[Test]
    public function it_offers_at_most_three_industries_as_shortcuts(): void
    {
        $repository = new InMemoryCompanyRepository();

        foreach (['Logistik', 'Logistik', 'Energie', 'Energie', 'Bauwesen', 'IT', 'Beratung'] as $i => $industry) {
            $repository->save(Company::create('Firma'.$i, $industry));
        }

        $top = (new CompanyList($repository))->getTopIndustries();

        self::assertCount(3, $top);
        self::assertSame(['Logistik' => 2, 'Energie' => 2], \array_slice($top, 0, 2, true));
    }

    #[Test]
    public function companies_without_an_industry_produce_no_shortcut(): void
    {
        $repository = new InMemoryCompanyRepository();
        $repository->save(Company::create('Ohne Branche'));

        self::assertSame([], (new CompanyList($repository))->getTopIndustries());
    }

    #[Test]
    public function a_short_list_is_not_truncated(): void
    {
        self::assertFalse($this->componentWith(5)->isTruncated());
    }

    #[Test]
    public function a_full_page_is_reported_as_truncated(): void
    {
        self::assertTrue($this->componentWith(50)->isTruncated());
    }

    private function componentWith(int $count): CompanyList
    {
        $repository = new InMemoryCompanyRepository();

        for ($i = 1; $i <= $count; ++$i) {
            $repository->save(Company::create('Firma'.$i));
        }

        return new CompanyList($repository);
    }
}
