<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Menu;

/**
 * Sammelt die Menueeintraege aller Module ein.
 *
 * Der Core kennt hierueber kein einziges Modul namentlich - er bekommt die
 * Provider als getaggten Iterator hereingereicht und fragt sie ab.
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
     * @return list<MenuItem> Absteigend nach Priority, bei Gleichstand alphabetisch.
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
