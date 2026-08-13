<?php

declare(strict_types=1);

namespace Crm\Deal\Tests;

use Crm\Deal\DealModule;
use Crm\Deal\UI\Menu\DealMenuProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class DealModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new DealModule();

        self::assertSame('deal', $module->name());
        self::assertSame(DealModule::NAME, $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
    }

    #[Test]
    public function it_declares_no_hard_dependencies(): void
    {
        // Firma und Kontakt sind optional: ohne die Module bleiben die
        // Verweise unaufloesbar, die Pipeline funktioniert trotzdem.
        self::assertSame([], (new DealModule())->dependencies());
    }

    #[Test]
    public function it_registers_its_own_doctrine_mapping(): void
    {
        $mapping = $this->prependInto('doctrine', 'twig')
            ->getExtensionConfig('doctrine')[0]['orm']['mappings']['CrmDeal'];

        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Crm\\Deal\\Domain', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        $paths = $this->prependInto('doctrine', 'twig')->getExtensionConfig('twig')[0]['paths'];

        self::assertContains('DealModule', $paths);
    }

    #[Test]
    public function it_stays_quiet_when_doctrine_is_not_installed(): void
    {
        self::assertSame([], $this->prependInto('twig')->getExtensionConfig('doctrine'));
    }

    #[Test]
    public function it_stays_quiet_when_twig_is_not_installed(): void
    {
        self::assertSame([], $this->prependInto('doctrine')->getExtensionConfig('twig'));
    }

    #[Test]
    public function the_pipeline_sits_at_the_top_of_the_navigation(): void
    {
        // Kontakte liegen bei 100 - die Pipeline ist der Einstieg im
        // Vertriebsalltag und gehoert davor.
        $item = iterator_to_array((new DealMenuProvider())->getMenuItems())[0];

        self::assertSame('deal.menu', $item->label);
        self::assertSame('deal_index', $item->route);
        self::assertGreaterThan(100, $item->priority);
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
            'DealModuleTest.php',
        );

        (new DealModule())->prependExtension($configurator, $builder);

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
