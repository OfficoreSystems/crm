<?php

declare(strict_types=1);

namespace Crm\User\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class UserMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        // Niedrige Priority: Verwaltung gehoert ans Ende der Navigation,
        // nicht vor die taegliche Arbeit.
        yield new MenuItem(
            label: 'user.menu',
            route: 'user_index',
            icon: 'users-cog',
            priority: 10,
        );
    }
}
