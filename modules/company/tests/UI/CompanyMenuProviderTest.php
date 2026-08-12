<?php

declare(strict_types=1);

namespace Crm\Company\Tests\UI;

use Crm\Company\UI\Menu\CompanyMenuProvider;
use Crm\SharedKernel\Menu\MenuItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompanyMenuProviderTest extends TestCase
{
    #[Test]
    public function it_offers_the_company_list(): void
    {
        $items = iterator_to_array((new CompanyMenuProvider())->getMenuItems());

        self::assertCount(1, $items);
        self::assertInstanceOf(MenuItem::class, $items[0]);
        self::assertSame('Firmen', $items[0]->label);
        self::assertSame('company_index', $items[0]->route);
    }

    #[Test]
    public function it_sorts_below_contacts_but_above_administration(): void
    {
        // Kontakte 100, Firmen 90, Benutzer 10.
        $priority = iterator_to_array((new CompanyMenuProvider())->getMenuItems())[0]->priority;

        self::assertLessThan(100, $priority);
        self::assertGreaterThan(10, $priority);
    }
}
