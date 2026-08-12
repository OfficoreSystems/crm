<?php

declare(strict_types=1);

namespace Crm\Dashboard\Tests;

use Crm\Dashboard\DashboardModule;
use Crm\Dashboard\UI\Menu\DashboardMenuProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class DashboardModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new DashboardModule();

        self::assertSame('dashboard', $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
        self::assertSame([], $module->dependencies());
    }

    #[Test]
    public function it_owns_neither_data_nor_domain_logic(): void
    {
        // Es rechnet nichts: jedes Modul liefert seine Kennzahlen fertig
        // aggregiert. Ein Doctrine-Mapping hier waere ein Fehler.
        $module = new DashboardModule();

        self::assertSame([], $this->prependInto('doctrine', 'twig')->getExtensionConfig('doctrine'));
        self::assertDirectoryDoesNotExist($module->getPath().'/src/Domain');
        self::assertDirectoryDoesNotExist($module->getPath().'/src/Infrastructure');
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        self::assertContains(
            'DashboardModule',
            $this->prependInto('twig')->getExtensionConfig('twig')[0]['paths'],
        );
    }

    #[Test]
    public function it_stays_quiet_when_twig_is_not_installed(): void
    {
        self::assertSame([], $this->prependInto()->getExtensionConfig('twig'));
    }

    #[Test]
    public function it_claims_the_top_of_the_navigation(): void
    {
        // Damit wird es zugleich das Ziel von "/" - der HomeController nimmt
        // den ersten Menueeintrag, ohne das Dashboard zu kennen.
        $item = iterator_to_array((new DashboardMenuProvider())->getMenuItems())[0];

        self::assertSame('dashboard_index', $item->route);
        self::assertGreaterThan(200, $item->priority, 'Muss ueber der Suche liegen.');
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
            'DashboardModuleTest.php',
        );

        (new DashboardModule())->prependExtension($configurator, $builder);

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
