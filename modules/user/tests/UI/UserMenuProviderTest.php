<?php

declare(strict_types=1);

namespace Crm\User\Tests\UI;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\User\UI\Menu\UserMenuProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserMenuProviderTest extends TestCase
{
    #[Test]
    public function it_offers_the_user_administration(): void
    {
        $items = iterator_to_array((new UserMenuProvider())->getMenuItems());

        self::assertCount(1, $items);
        self::assertInstanceOf(MenuItem::class, $items[0]);
        self::assertSame('Benutzer', $items[0]->label);
        self::assertSame('user_index', $items[0]->route);
    }

    #[Test]
    public function administration_sorts_behind_the_daily_work(): void
    {
        // Contact liegt bei 100. Verwaltung gehoert ans Ende der Navigation.
        $items = iterator_to_array((new UserMenuProvider())->getMenuItems());

        self::assertLessThan(100, $items[0]->priority);
    }
}
