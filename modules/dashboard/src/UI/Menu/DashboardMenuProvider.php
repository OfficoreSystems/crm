<?php

declare(strict_types=1);

namespace Crm\Dashboard\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class DashboardMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        // Hoechste Priority im System: die Startseite. Damit landet auch die
        // Weiterleitung von "/" hier, ohne dass der Core das Dashboard
        // benennen muesste - der HomeController nimmt schlicht den ersten
        // Menueeintrag.
        yield new MenuItem(
            label: 'Übersicht',
            route: 'dashboard_index',
            icon: 'layout-dashboard',
            priority: 300,
        );
    }
}
