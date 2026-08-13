<?php

declare(strict_types=1);

namespace App\Twig;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Makes the navigation available to the layout.
 *
 * The only place where the core speaks about modules - and even here only
 * through the registry from the shared kernel, never about a concrete module.
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
