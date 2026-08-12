<?php

declare(strict_types=1);

namespace Crm\Search\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class SearchMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        // Ueber allem: die Suche ist der schnellste Weg zu irgendetwas.
        yield new MenuItem(
            label: 'Suche',
            route: 'search_index',
            icon: 'search',
            priority: 200,
        );
    }
}
