<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests;

use Crm\Calendar\CalendarModule;
use Crm\Calendar\UI\Menu\CalendarMenuProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class CalendarModuleTest extends TestCase
{
    #[Test]
    public function it_describes_itself(): void
    {
        $module = new CalendarModule();

        self::assertSame('calendar', $module->name());
        self::assertSame(CalendarModule::NAME, $module->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $module->version());
    }

    #[Test]
    public function it_declares_no_hard_dependencies(): void
    {
        self::assertSame([], (new CalendarModule())->dependencies());
    }

    #[Test]
    public function the_feed_lives_in_the_public_area(): void
    {
        // Der Praefix muss zu access_control in
        // config/packages/security.yaml passen - sonst laeuft jeder Abruf
        // durch Outlook auf die Anmeldeseite, und der Kalender bleibt still
        // leer. Diese Zusicherung ist der einzige Ort, an dem beide Seiten
        // aufeinandertreffen.
        self::assertStringStartsWith('/oeffentlich/', CalendarModule::FEED_PREFIX);
    }

    #[Test]
    public function it_registers_its_own_doctrine_mapping(): void
    {
        $mapping = $this->prependInto('doctrine', 'twig')
            ->getExtensionConfig('doctrine')[0]['orm']['mappings']['CrmCalendar'];

        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Crm\\Calendar\\Domain', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    #[Test]
    public function it_registers_its_own_twig_namespace(): void
    {
        $paths = $this->prependInto('doctrine', 'twig')->getExtensionConfig('twig')[0]['paths'];

        self::assertContains('CalendarModule', $paths);
    }

    #[Test]
    public function it_touches_the_security_configuration_not_at_all(): void
    {
        // security.access_control laesst sich - wie security.firewalls - nicht
        // aus einem Modul heraus prependen ("cannot be overwritten"). Ein
        // Versuch waere kein stiller Fehler, sondern ein Abbruch beim
        // Container-Build; dieser Test haelt fest, dass wir es gar nicht erst
        // probieren.
        self::assertSame([], $this->prependInto('doctrine', 'twig', 'security')->getExtensionConfig('security'));
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
    public function the_calendar_sits_between_pipeline_and_contacts(): void
    {
        // Was heute ansteht, schaut man haeufiger an als eine Kontaktliste -
        // aber die Pipeline bleibt der Einstieg.
        $item = iterator_to_array((new CalendarMenuProvider())->getMenuItems())[0];

        self::assertSame('Kalender', $item->label);
        self::assertSame('calendar_index', $item->route);
        self::assertGreaterThan(100, $item->priority);
        self::assertLessThan(110, $item->priority);
    }

    private function prependInto(string ...$availableExtensions): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'dev');

        foreach ($availableExtensions as $alias) {
            $builder->registerExtension($this->stubExtension($alias));
        }

        $instanceof = [];
        $configurator = new ContainerConfigurator(
            $builder,
            new PhpFileLoader($builder, new FileLocator(__DIR__)),
            $instanceof,
            __DIR__,
            'CalendarModuleTest.php',
        );

        (new CalendarModule())->prependExtension($configurator, $builder);

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
