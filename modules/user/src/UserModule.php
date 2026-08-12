<?php

declare(strict_types=1);

namespace Crm\User;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class UserModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'user';

    protected string $extensionAlias = 'crm_user';

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
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => ['uuid' => UuidType::class],
                ],
                'orm' => [
                    'mappings' => [
                        'CrmUser' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath().'/src/Domain',
                            'prefix' => 'Crm\\User\\Domain',
                            'alias' => 'User',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'UserModule'],
            ]);
        }

        // Die Firewall wird hier bewusst *nicht* geprependet.
        //
        // security.firewalls ist bei Symfony ein prototypisierter Knoten und
        // muss vollstaendig aus einer Konfigurationsdatei kommen - ein Prepend
        // bricht mit "You are not allowed to define new elements for path
        // security.firewalls" ab. Der Core definiert die Firewall deshalb
        // einmal und verweist auf die Service-ID crm.security.user_provider.
        // Dieses Modul haengt sich dort in config/services.php ein.
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }
}
