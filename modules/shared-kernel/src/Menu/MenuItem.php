<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Menu;

/**
 * An entry in the global navigation.
 *
 * Deliberately a plain value without behaviour: modules create it, the core
 * renders it. Both sides only have to agree on these four fields.
 */
final readonly class MenuItem
{
    /**
     * @param string $label    Translation key, not a finished text - the layout
     *                         puts it through |trans.
     * @param string $route    Symfony route name, not the URL.
     * @param string $icon     Icon identifier, interpreted by the template.
     * @param int    $priority Higher = further to the front. Ties are sorted by label.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon = 'dot',
        public int $priority = 0,
    ) {
    }
}
