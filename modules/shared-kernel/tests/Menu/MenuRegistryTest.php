<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;
use Crm\SharedKernel\Menu\MenuRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MenuRegistryTest extends TestCase
{
    #[Test]
    public function it_returns_an_empty_menu_when_no_module_is_installed(): void
    {
        self::assertSame([], (new MenuRegistry([]))->items());
    }

    #[Test]
    public function it_sorts_by_priority_descending(): void
    {
        $registry = new MenuRegistry([
            self::provider(new MenuItem('Berichte', 'report_index', priority: 10)),
            self::provider(new MenuItem('Kontakte', 'contact_index', priority: 100)),
            self::provider(new MenuItem('Rechnungen', 'invoice_index', priority: 50)),
        ]);

        self::assertSame(
            ['Kontakte', 'Rechnungen', 'Berichte'],
            array_map(static fn (MenuItem $i): string => $i->label, $registry->items()),
        );
    }

    #[Test]
    public function it_falls_back_to_alphabetical_order_on_equal_priority(): void
    {
        // Wichtig, damit die Navigation nicht je nach Installationsreihenfolge
        // der Module anders aussieht.
        $registry = new MenuRegistry([
            self::provider(new MenuItem('Zeiterfassung', 'time_index', priority: 0)),
            self::provider(new MenuItem('Angebote', 'quote_index', priority: 0)),
        ]);

        self::assertSame(
            ['Angebote', 'Zeiterfassung'],
            array_map(static fn (MenuItem $i): string => $i->label, $registry->items()),
        );
    }

    #[Test]
    public function it_flattens_multiple_items_from_a_single_provider(): void
    {
        $registry = new MenuRegistry([
            self::provider(
                new MenuItem('Kontakte', 'contact_index', priority: 100),
                new MenuItem('Firmen', 'company_index', priority: 90),
            ),
        ]);

        self::assertCount(2, $registry->items());
    }

    private static function provider(MenuItem ...$items): MenuProviderInterface
    {
        return new class($items) implements MenuProviderInterface {
            /**
             * @param list<MenuItem> $items
             */
            public function __construct(private readonly array $items)
            {
            }

            public function getMenuItems(): iterable
            {
                yield from $this->items;
            }
        };
    }
}
