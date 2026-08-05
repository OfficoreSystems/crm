<?php

declare(strict_types=1);

namespace App\Twig;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Macht die Navigation im Layout verfuegbar.
 *
 * Die einzige Stelle, an der der Core ueber Module spricht - und auch hier nur
 * ueber die Registry aus dem shared-kernel, nie ueber ein konkretes Modul.
 */
final class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuRegistry $menu,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('crm_menu', $this->items(...)),
        ];
    }

    /**
     * @return list<MenuItem>
     */
    public function items(): array
    {
        return $this->menu->items();
    }
}
