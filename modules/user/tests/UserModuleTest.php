<?php

declare(strict_types=1);

namespace Crm\User\Tests;

use Crm\User\UserModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class UserModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new UserModule();

        self::assertSame('user', $module->name());
        self::assertSame(UserModule::NAME, $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
        self::assertSame([], $module->dependencies());
    }

    #[Test]
    public function it_registers_its_own_doctrine_mapping(): void
    {
        $builder = $this->prependInto('doctrine', 'twig', 'security');

        $mapping = $builder->getExtensionConfig('doctrine')[0]['orm']['mappings']['CrmUser'];

        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Crm\\User\\Domain', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        $builder = $this->prependInto('doctrine', 'twig', 'security');

        $paths = $builder->getExtensionConfig('twig')[0]['paths'];

        self::assertContains('UserModule', $paths);
        self::assertDirectoryExists((string) array_key_first($paths));
    }

    #[Test]
    public function it_does_not_touch_the_security_configuration(): void
    {
        // security.firewalls ist bei Symfony ein prototypisierter Knoten und
        // muss aus einer einzigen Datei kommen. Wuerde dieses Modul dort
        // etwas prependen, braeche der Container-Build mit "You are not
        // allowed to define new elements for path security.firewalls" ab.
        // Stattdessen ueberschreibt das Modul nur den Alias
        // crm.security.user_provider in config/services.php.
        $builder = $this->prependInto('doctrine', 'twig', 'security');

        self::assertSame([], $builder->getExtensionConfig('security'));
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
    public function its_path_points_at_the_module_root_not_at_src(): void
    {
        $module = new UserModule();

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
            'UserModuleTest.php',
        );

        (new UserModule())->prependExtension($configurator, $builder);

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
