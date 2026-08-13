<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Menu;

/**
 * Extension point: a module registers its navigation entries.
 *
 * Implementations are tagged with `crm.menu_provider` automatically through
 * registerForAutoconfiguration() - a module has to do nothing in its service
 * configuration for it.
 */
interface MenuProviderInterface
{
    /**
     * @return iterable<MenuItem>
     */
    public function getMenuItems(): iterable;
}
