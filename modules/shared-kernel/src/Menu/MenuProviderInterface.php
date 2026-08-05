<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Menu;

/**
 * Extension-Point: ein Modul meldet seine Navigationseintraege an.
 *
 * Implementierungen werden ueber registerForAutoconfiguration() automatisch
 * mit `crm.menu_provider` getaggt - ein Modul muss dafuer nichts in der
 * Service-Konfiguration tun.
 */
interface MenuProviderInterface
{
    /**
     * @return iterable<MenuItem>
     */
    public function getMenuItems(): iterable;
}
