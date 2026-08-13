<?php

declare(strict_types=1);

namespace Crm\Company\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class CompanyMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        // Knapp unter Kontakte (100): beides taegliche Arbeit, Kontakte sind
        // der haeufigere Einstieg.
        yield new MenuItem(
            label: 'company.menu',
            route: 'company_index',
            icon: 'building',
            priority: 90,
        );
    }
}
