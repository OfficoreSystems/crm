<?php

declare(strict_types=1);

namespace Crm\Contact\Tests;

use Crm\Contact\ContactModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Das Modul konfiguriert sich selbst. Diese Tests halten fest, dass es das
 * auch weiterhin tut - waeren Doctrine-Mapping oder Twig-Pfad einmal in der
 * App gelandet, muesste man sie bei jedem neuen Modul erneut dort eintragen.
 */
final class ContactModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new ContactModule();

        self::assertSame('contact', $module->name());
        self::assertSame(ContactModule::NAME, $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
    }

    #[Test]
    public function it_depends_on_no_other_module(): void
    {
        // Das Referenzmodul muss allein lauffaehig bleiben - es wird kopiert.
        self::assertSame([], (new ContactModule())->dependencies());
    }

    #[Test]
    public function it_registers_its_own_doctrine_mapping(): void
    {
        $builder = $this->prependInto('doctrine', 'twig');

        $config = $builder->getExtensionConfig('doctrine');
        $mapping = $config[0]['orm']['mappings']['CrmContact'];

        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Crm\\Contact\\Domain', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    #[Test]
    public function it_registers_the_uuid_type(): void
    {
        $builder = $this->prependInto('doctrine', 'twig');

        $config = $builder->getExtensionConfig('doctrine');

        self::assertArrayHasKey('uuid', $config[0]['dbal']['types']);
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        $builder = $this->prependInto('doctrine', 'twig');

        $paths = $builder->getExtensionConfig('twig')[0]['paths'];

        self::assertContains('ContactModule', $paths);
        self::assertDirectoryExists((string) array_key_first($paths));
    }

    #[Test]
    public function it_stays_quiet_when_doctrine_is_not_installed(): void
    {
        // Ohne die hasExtension()-Guards braeche der Container-Build ab,
        // statt das Modul einfach reduziert laufen zu lassen.
        $builder = $this->prependInto('twig');

        self::assertSame([], $builder->getExtensionConfig('doctrine'));
    }

    #[Test]
    public function it_stays_quiet_when_twig_is_not_installed(): void
    {
        $builder = $this->prependInto('doctrine');

        self::assertSame([], $builder->getExtensionConfig('twig'));
    }

    #[Test]
    public function its_path_points_at_the_module_root_not_at_src(): void
    {
        $module = new ContactModule();

        self::assertFileExists($module->getPath().'/config/services.php');
        self::assertFileExists($module->getPath().'/config/routes.php');
        self::assertDirectoryExists($module->getPath().'/templates');
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
            'ContactModuleTest.php',
        );

        (new ContactModule())->prependExtension($configurator, $builder);

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
