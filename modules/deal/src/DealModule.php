<?php

declare(strict_types=1);

namespace Crm\Deal;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DealModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'deal';

    protected string $extensionAlias = 'crm_deal';

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
        // Firma und Kontakt sind optional: ohne die Module bleiben die
        // Verweise unaufloesbar, die Pipeline funktioniert trotzdem.
        return [];
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => ['uuid' => UuidType::class],
                ],
                'orm' => [
                    'mappings' => [
                        'CrmDeal' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath().'/src/Domain',
                            'prefix' => 'Crm\\Deal\\Domain',
                            'alias' => 'Deal',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'DealModule'],
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
