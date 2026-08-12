<?php

declare(strict_types=1);

namespace Crm\Activity\Tests;

use Crm\Activity\ActivityModule;
use Crm\Activity\UI\Menu\ActivityMenuProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class ActivityModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new ActivityModule();

        self::assertSame('activity', $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
    }

    #[Test]
    public function it_declares_no_dependencies_although_it_needs_subjects(): void
    {
        // Absicht: ohne registrierte Resolver bleibt die Timeline leer, aber
        // die Anwendung laeuft. Sobald ein Modul dazukommt, ist es ohne
        // Aenderung hier nutzbar - das ist der Sinn des Extension-Points.
        self::assertSame([], (new ActivityModule())->dependencies());
    }

    #[Test]
    public function it_registers_its_own_doctrine_mapping(): void
    {
        $mapping = $this->prependInto('doctrine', 'twig')
            ->getExtensionConfig('doctrine')[0]['orm']['mappings']['CrmActivity'];

        self::assertSame('attribute', $mapping['type']);
        self::assertSame('Crm\\Activity\\Domain', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        self::assertContains(
            'ActivityModule',
            $this->prependInto('doctrine', 'twig')->getExtensionConfig('twig')[0]['paths'],
        );
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
    public function it_offers_a_menu_entry(): void
    {
        $item = iterator_to_array((new ActivityMenuProvider())->getMenuItems())[0];

        self::assertSame('activity_index', $item->route);
        self::assertNotSame('', $item->label);
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
            'ActivityModuleTest.php',
        );

        (new ActivityModule())->prependExtension($configurator, $builder);

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
