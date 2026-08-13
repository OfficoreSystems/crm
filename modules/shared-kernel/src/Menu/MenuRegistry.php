<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Menu;

/**
 * Collects the menu entries of every module.
 *
 * Through this the core knows not a single module by name - it receives the
 * providers as a tagged iterator and asks them.
 */
final class MenuRegistry
{
    /**
     * @param iterable<MenuProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * Descending by priority; by label on a tie.
     *
     * The label is a translation key, so the secondary sort is not alphabetical
     * in the display language. The registry cannot translate - it sits in the
     * contract layer. The return would be small anyway: ties are rare, and an
     * order that changes with the language is not better either.
     *
     * @return list<MenuItem>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getMenuItems() as $item) {
                $items[] = $item;
            }
        }

        usort(
            $items,
            static fn (MenuItem $a, MenuItem $b): int => [$b->priority, $a->label] <=> [$a->priority, $b->label],
        );

        return $items;
    }
}
