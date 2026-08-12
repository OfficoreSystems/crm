<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests;

use Crm\SharedKernel\CrmSharedKernelBundle;
use Crm\SharedKernel\Menu\MenuProviderInterface;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;
use Crm\SharedKernel\Module\CrmModuleInterface;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Die Autoconfiguration ist der Vertrag, auf den sich jedes Modul verlaesst:
 * Interface implementieren genuegt, kein Eintrag im Core noetig. Faellt einer
 * der Tags weg, meldet sich kein Modul mehr an - und zwar lautlos.
 */
final class CrmSharedKernelBundleTest extends TestCase
{
    #[Test]
    public function it_tags_menu_providers_automatically(): void
    {
        $tags = $this->autoconfiguredTagsFor(MenuProviderInterface::class);

        self::assertArrayHasKey('crm.menu_provider', $tags);
    }

    #[Test]
    public function it_tags_modules_automatically(): void
    {
        $tags = $this->autoconfiguredTagsFor(CrmModuleInterface::class);

        self::assertArrayHasKey('crm.module', $tags);
    }

    #[Test]
    public function it_tags_subject_resolvers_automatically(): void
    {
        $tags = $this->autoconfiguredTagsFor(SubjectResolverInterface::class);

        self::assertArrayHasKey('crm.subject_resolver', $tags);
    }

    #[Test]
    public function it_registers_exactly_these_extension_points(): void
    {
        // Absichtlich streng: ein zusaetzlicher Extension-Point ist eine
        // Erweiterung der oeffentlichen Schnittstelle und soll auffallen.
        // Wer hier etwas ergaenzt, soll das bewusst tun.
        $container = new ContainerBuilder();
        (new CrmSharedKernelBundle())->build($container);

        self::assertSame(
            [
                MenuProviderInterface::class,
                CrmModuleInterface::class,
                SubjectResolverInterface::class,
                MetricProviderInterface::class,
            ],
            array_keys($container->getAutoconfiguredInstanceof()),
        );
    }

    #[Test]
    public function it_tags_metric_providers_automatically(): void
    {
        $tags = $this->autoconfiguredTagsFor(MetricProviderInterface::class);

        self::assertArrayHasKey('crm.metric_provider', $tags);
    }

    #[Test]
    public function its_path_points_at_the_module_root_not_at_src(): void
    {
        // AbstractBundle leitet den Pfad aus der Klassendatei ab. Stimmt er
        // nicht, findet loadExtension() die config/services.php nicht.
        $bundle = new CrmSharedKernelBundle();

        self::assertFileExists($bundle->getPath().'/config/services.php');
        self::assertFileExists($bundle->getPath().'/composer.json');
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function autoconfiguredTagsFor(string $interface): array
    {
        $container = new ContainerBuilder();
        (new CrmSharedKernelBundle())->build($container);

        $definitions = $container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey($interface, $definitions);

        return $definitions[$interface]->getTags();
    }
}
