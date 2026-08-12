<?php

declare(strict_types=1);

namespace Crm\Activity;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ActivityModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'activity';

    protected string $extensionAlias = 'crm_activity';

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
        // Kein Modul ist Pflicht. Ohne registrierte Resolver bleibt die
        // Timeline leer, aber die Anwendung laeuft - und sobald eines
        // dazukommt, ist es ohne Aenderung hier nutzbar.
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
                        'CrmActivity' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath().'/src/Domain',
                            'prefix' => 'Crm\\Activity\\Domain',
                            'alias' => 'Activity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'ActivityModule'],
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
