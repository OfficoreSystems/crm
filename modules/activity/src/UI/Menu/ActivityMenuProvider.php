<?php

declare(strict_types=1);

namespace Crm\Activity\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class ActivityMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        yield new MenuItem(
            label: 'activity.menu',
            route: 'activity_index',
            icon: 'clock',
            priority: 95,
        );
    }
}
