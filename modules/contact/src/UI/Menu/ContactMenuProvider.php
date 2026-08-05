<?php

declare(strict_types=1);

namespace Crm\Contact\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

/**
 * Meldet den Navigationseintrag des Moduls an.
 *
 * Kein Eintrag im Core noetig: das Interface reicht, den Rest erledigt die
 * Autoconfiguration im CrmSharedKernelBundle.
 */
final class ContactMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        yield new MenuItem(
            label: 'Kontakte',
            route: 'contact_index',
            icon: 'users',
            priority: 100,
        );
    }
}
