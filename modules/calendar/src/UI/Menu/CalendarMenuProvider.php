<?php

declare(strict_types=1);

namespace Crm\Calendar\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class CalendarMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        // Zwischen Pipeline und Kontakten: was heute ansteht, schaut man
        // haeufiger an als eine Kontaktliste.
        yield new MenuItem(
            label: 'calendar.menu',
            route: 'calendar_index',
            icon: 'calendar',
            priority: 105,
        );
    }
}
