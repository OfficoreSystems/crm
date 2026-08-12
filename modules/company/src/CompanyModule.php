<?php

declare(strict_types=1);

namespace Crm\Company;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class CompanyModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'company';

    protected string $extensionAlias = 'crm_company';

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
                        'CrmCompany' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath().'/src/Domain',
                            'prefix' => 'Crm\\Company\\Domain',
                            'alias' => 'Company',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'CompanyModule'],
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
