<?php

declare(strict_types=1);

namespace Crm\Search;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Das erste Modul ohne eigene Daten.
 *
 * Kein Doctrine-Mapping, keine Tabelle, keine Migration - es fragt nur die
 * SubjectResolverRegistry und sortiert das Ergebnis. Dass das reicht, ist der
 * beste Beleg dafuer, dass der Extension-Point richtig geschnitten ist: ein
 * neues Modul wird durchsuchbar, indem es einen Resolver mitbringt, und
 * dieses Modul erfaehrt davon nichts.
 */
final class SearchModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'search';

    protected string $extensionAlias = 'crm_search';

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
        // Kein Doctrine-Block: dieses Modul speichert nichts.
        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'SearchModule'],
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
