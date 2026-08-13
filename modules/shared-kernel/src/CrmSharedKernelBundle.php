<?php

declare(strict_types=1);

namespace Crm\SharedKernel;

use Crm\SharedKernel\Dashboard\MetricProviderInterface;
use Crm\SharedKernel\Menu\MenuProviderInterface;
use Crm\SharedKernel\Module\CrmModuleInterface;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
use Crm\SharedKernel\Infrastructure\Security\RecordVisibilityFilter;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the extension points through which modules plug into the core.
 *
 * There is deliberately no list of modules here: the core only learns at
 * container compile time who has attached themselves to the tags.
 */
final class CrmSharedKernelBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Autoconfiguration instead of manual tags: a module only implements the
        // interface and is registered by that - no entry in the core needed.
        $container->registerForAutoconfiguration(MenuProviderInterface::class)
            ->addTag('crm.menu_provider');

        $container->registerForAutoconfiguration(CrmModuleInterface::class)
            ->addTag('crm.module');

        // Makes a module's records referenceable as a polymorphic subject - for
        // activities, later documents and emails.
        $container->registerForAutoconfiguration(SubjectResolverInterface::class)
            ->addTag('crm.subject_resolver');

        // Figures for the home page, pre-aggregated by the delivering module.
        $container->registerForAutoconfiguration(MetricProviderInterface::class)
            ->addTag('crm.metric_provider');

        // Tells the voter who owns a module's records.
        $container->registerForAutoconfiguration(RecordOwnershipInterface::class)
            ->addTag('crm.record_ownership');
    }

    /**
     * Registers the visibility filter with Doctrine.
     *
     * Deliberately here and not in the application configuration: the filter
     * belongs to the contract layer, and a project should not be able to forget
     * it. It is still only enabled per request and only with a signed-in user -
     * see RecordVisibilityConfigurator.
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine')) {
            return;
        }

        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'filters' => [
                    RecordVisibilityFilter::NAME => [
                        'class' => RecordVisibilityFilter::class,
                        // Off by default: without parameters it would do
                        // nothing, but an enabled filter without values is a
                        // trap for the next reader.
                        'enabled' => false,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }
}
