<?php

declare(strict_types=1);

namespace Crm\Company\Tests;

use Crm\Company\CompanyModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class CompanyModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new CompanyModule();

        self::assertSame('company', $module->name());
        self::assertSame(CompanyModule::NAME, $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
        self::assertSame([], $module->dependencies());
    }

    #[Test]
    public function it_registers_its_own_doctrine_mapping(): void
    {
        $mapping = $this->prependInto('doctrine', 'twig')
            ->getExtensionConfig('doctrine')[0]['orm']['mappings']['CrmCompany'];

        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Crm\\Company\\Domain', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        $paths = $this->prependInto('doctrine', 'twig')->getExtensionConfig('twig')[0]['paths'];

        self::assertContains('CompanyModule', $paths);
        self::assertDirectoryExists((string) array_key_first($paths));
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
        $module = new CompanyModule();

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
            'CompanyModuleTest.php',
        );

        (new CompanyModule())->prependExtension($configurator, $builder);

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
