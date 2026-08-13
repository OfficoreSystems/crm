<?php

declare(strict_types=1);

namespace Crm\Document\Tests;

use Crm\Document\DocumentModule;
use Crm\Document\UI\Menu\DocumentMenuProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class DocumentModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new DocumentModule();

        self::assertSame('document', $module->name());
        self::assertSame(DocumentModule::NAME, $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
    }

    #[Test]
    public function it_declares_no_hard_dependencies(): void
    {
        // Dokumente haengen an Subjekten, die andere Module aufloesen. Gibt es
        // keines, laesst sich nichts hochladen - die Anwendung startet
        // trotzdem.
        self::assertSame([], (new DocumentModule())->dependencies());
    }

    #[Test]
    public function it_registers_its_own_doctrine_mapping(): void
    {
        $mapping = $this->prependInto('doctrine', 'twig', 'flysystem')
            ->getExtensionConfig('doctrine')[0]['orm']['mappings']['CrmDocument'];

        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Crm\\Document\\Domain', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        $paths = $this->prependInto('doctrine', 'twig', 'flysystem')->getExtensionConfig('twig')[0]['paths'];

        self::assertContains('DocumentModule', $paths);
    }

    #[Test]
    public function it_brings_its_own_storage(): void
    {
        // Der Speicher gehoert zum Modul, nicht zur Anwendung. Wer das Modul
        // entfernt, entfernt auch seinen Speicher - ohne eine Datei im Core
        // anzufassen.
        $storages = $this->prependInto('doctrine', 'twig', 'flysystem')
            ->getExtensionConfig('flysystem')[0]['storages'];

        self::assertArrayHasKey(DocumentModule::STORAGE, $storages);
    }

    #[Test]
    public function in_tests_it_uses_a_local_directory(): void
    {
        // Sonst braeuchte jeder Testlauf einen laufenden MinIO-Container - und
        // damit waere die Suite nicht mehr das, was man vor dem Commit eben
        // durchlaufen laesst.
        $storage = $this->prepend(['flysystem'], 'test')
            ->getExtensionConfig('flysystem')[0]['storages'][DocumentModule::STORAGE];

        self::assertArrayHasKey('local', $storage);
    }

    #[Test]
    public function outside_tests_it_uses_the_object_store(): void
    {
        $storage = $this->prepend(['flysystem'], 'prod')
            ->getExtensionConfig('flysystem')[0]['storages'][DocumentModule::STORAGE];

        self::assertArrayHasKey('asyncaws', $storage);
        self::assertStringContainsString('%env(', $storage['asyncaws']['bucket'], 'Der Bucket kommt aus der Umgebung, nie aus dem Repo.');
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
    public function it_stays_quiet_when_flysystem_is_not_installed(): void
    {
        // Ohne die Bibliothek startet die Anwendung trotzdem. Hochladen geht
        // dann nicht - aber das ist ein verstaendlicher Fehler beim Versuch,
        // kein Abbruch beim Container-Build.
        self::assertSame([], $this->prependInto('doctrine', 'twig')->getExtensionConfig('flysystem'));
    }

    #[Test]
    public function documents_sit_low_in_the_navigation(): void
    {
        // Man sucht sie ueber den Datensatz, an dem sie haengen - die Liste
        // ist der Nachschlageweg, nicht der Einstieg.
        $item = iterator_to_array((new DocumentMenuProvider())->getMenuItems())[0];

        self::assertSame('document.menu', $item->label);
        self::assertSame('document_index', $item->route);
        self::assertLessThan(100, $item->priority);
    }

    private function prependInto(string ...$availableExtensions): ContainerBuilder
    {
        return $this->prepend($availableExtensions, 'dev');
    }

    /**
     * @param list<string> $availableExtensions
     */
    private function prepend(array $availableExtensions, string $environment): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', $environment);

        foreach ($availableExtensions as $alias) {
            $builder->registerExtension($this->stubExtension($alias));
        }

        $instanceof = [];
        $configurator = new ContainerConfigurator(
            $builder,
            new PhpFileLoader($builder, new FileLocator(__DIR__)),
            $instanceof,
            __DIR__,
            'DocumentModuleTest.php',
        );

        (new DocumentModule())->prependExtension($configurator, $builder);

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
