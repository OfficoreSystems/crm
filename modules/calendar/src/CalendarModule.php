<?php

declare(strict_types=1);

namespace Crm\Calendar;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class CalendarModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'calendar';

    /**
     * Der Pfad des ICS-Feeds.
     *
     * Liegt im oeffentlichen Bereich, weil Outlook und Google keine Sitzung
     * mitbringen koennen - siehe access_control in
     * config/packages/security.yaml. Der Praefix dort ist generisch; welches
     * Modul ihn benutzt, steht hier und nur hier.
     */
    public const FEED_PREFIX = '/public/calendar';

    protected string $extensionAlias = 'crm_calendar';

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
        // Termine koennen an einem Datensatz haengen, muessen aber nicht -
        // ein Teammeeting gehoert zu niemandem im CRM.
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
                        'CrmCalendar' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath().'/src/Domain',
                            'prefix' => 'Crm\\Calendar\\Domain',
                            'alias' => 'Calendar',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'CalendarModule'],
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
