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
     * Absteigend nach Priority; bei Gleichstand nach dem Label.
     *
     * Das Label ist ein Uebersetzungsschluessel, die Zweitsortierung also
     * nicht alphabetisch in der Anzeigesprache. Uebersetzen kann die Registry
     * nicht - sie liegt in der Vertragsschicht. Der Gegenwert waere ohnehin
     * gering: Gleichstand ist selten, und eine Reihenfolge, die sich mit der
     * Sprache aendert, ist auch nicht besser.
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
