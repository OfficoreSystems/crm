<?php

declare(strict_types=1);

namespace Crm\Search\Tests;

use Crm\Search\SearchModule;
use Crm\Search\UI\Menu\SearchMenuProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class SearchModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new SearchModule();

        self::assertSame('search', $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
        self::assertSame([], $module->dependencies());
    }

    #[Test]
    public function it_registers_no_doctrine_mapping_because_it_owns_no_data(): void
    {
        // Der Beleg, dass ein Modul keine Tabelle braucht: search fragt nur
        // die Registry. Ein Doctrine-Block hier waere ein Fehler.
        self::assertSame([], $this->prependInto('doctrine', 'twig')->getExtensionConfig('doctrine'));
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        self::assertContains(
            'SearchModule',
            $this->prependInto('twig')->getExtensionConfig('twig')[0]['paths'],
        );
    }

    #[Test]
    public function it_stays_quiet_when_twig_is_not_installed(): void
    {
        self::assertSame([], $this->prependInto()->getExtensionConfig('twig'));
    }

    #[Test]
    public function it_has_no_migrations_directory(): void
    {
        self::assertDirectoryDoesNotExist((new SearchModule())->getPath().'/migrations');
    }

    #[Test]
    public function the_search_sits_above_everything_in_the_navigation(): void
    {
        // Die Pipeline liegt bei 110 - die Suche ist der schnellste Weg zu
        // irgendetwas und gehoert darueber.
        $item = iterator_to_array((new SearchMenuProvider())->getMenuItems())[0];

        self::assertSame('search_index', $item->route);
        self::assertGreaterThan(110, $item->priority);
    }

    private function prependInto(string ...$availableExtensions): ContainerBuilder
    {
        $builder = new ContainerBuilder();

        foreach ($availableExtensions as $alias) {
            $builder->registerExtension($this->stubExtension($alias));
        }

        $instanceof = [];
        $configurator = new ContainerConfigurator(
            $builder,
            new PhpFileLoader($builder, new FileLocator(__DIR__)),
            $instanceof,
            __DIR__,
            'SearchModuleTest.php',
        );

        (new SearchModule())->prependExtension($configurator, $builder);

        return $builder;
    }

    private function stubExtension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
