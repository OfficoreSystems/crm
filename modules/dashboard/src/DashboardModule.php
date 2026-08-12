<?php

declare(strict_types=1);

namespace Crm\Dashboard;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Das zweite Modul ohne eigene Daten.
 *
 * Es rechnet nichts und fragt keine fremde Tabelle ab: jedes Modul liefert
 * seine Kennzahlen fertig aggregiert ueber MetricProviderInterface. Das
 * Dashboard sortiert sie und zeigt sie an.
 */
final class DashboardModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'dashboard';

    protected string $extensionAlias = 'crm_dashboard';

    public function name(): string
    {
        return self::NAME;
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'DashboardModule'],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }
}
