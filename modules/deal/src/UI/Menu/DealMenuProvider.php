<?php

declare(strict_types=1);

namespace Crm\Deal\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class DealMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        // Hoechste Priority: die Pipeline ist der Einstieg im Vertriebsalltag.
        yield new MenuItem(
            label: 'Pipeline',
            route: 'deal_index',
            icon: 'trending-up',
            priority: 110,
        );
    }
}
