<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\MenuExtension;
use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;
use Crm\SharedKernel\Menu\MenuRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

final class MenuExtensionTest extends TestCase
{
    #[Test]
    public function it_exposes_exactly_one_twig_function(): void
    {
        $functions = (new MenuExtension(new MenuRegistry([])))->getFunctions();

        self::assertCount(1, $functions);
        self::assertInstanceOf(TwigFunction::class, $functions[0]);
        self::assertSame('crm_menu', $functions[0]->getName());
    }

    #[Test]
    public function it_returns_an_empty_menu_without_modules(): void
    {
        self::assertSame([], (new MenuExtension(new MenuRegistry([])))->items());
    }

    #[Test]
    public function it_passes_the_registry_order_through(): void
    {
        // Der Core sortiert nicht selbst - er reicht durch, was die Registry
        // liefert. Sonst gaebe es zwei Stellen, die die Reihenfolge bestimmen.
        $extension = new MenuExtension(new MenuRegistry([
            $this->provider(new MenuItem('Berichte', 'report_index', priority: 10)),
            $this->provider(new MenuItem('Kontakte', 'contact_index', priority: 100)),
        ]));

        self::assertSame(
            ['Kontakte', 'Berichte'],
            array_map(static fn (MenuItem $i): string => $i->label, $extension->items()),
        );
    }

    private function provider(MenuItem ...$items): MenuProviderInterface
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
