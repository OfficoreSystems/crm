<?php

declare(strict_types=1);

namespace Crm\SharedKernel;

use Crm\SharedKernel\Dashboard\MetricProviderInterface;
use Crm\SharedKernel\Menu\MenuProviderInterface;
use Crm\SharedKernel\Module\CrmModuleInterface;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registriert die Extension-Points, ueber die Module an den Core andocken.
 *
 * Hier steht bewusst keine Liste von Modulen: der Core erfaehrt erst zur
 * Compile-Zeit des Containers, wer sich an die Tags gehaengt hat.
 */
final class CrmSharedKernelBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Autoconfiguration statt manueller Tags: ein Modul implementiert nur
        // das Interface und ist damit angemeldet - kein Eintrag im Core noetig.
        $container->registerForAutoconfiguration(MenuProviderInterface::class)
            ->addTag('crm.menu_provider');

        $container->registerForAutoconfiguration(CrmModuleInterface::class)
            ->addTag('crm.module');

        // Macht die Datensaetze eines Moduls als polymorphes Subjekt
        // verweisbar - fuer Aktivitaeten, spaeter Dokumente und E-Mails.
        $container->registerForAutoconfiguration(SubjectResolverInterface::class)
            ->addTag('crm.subject_resolver');

        // Kennzahlen fuer die Startseite, fertig aggregiert vom liefernden
        // Modul.
        $container->registerForAutoconfiguration(MetricProviderInterface::class)
            ->addTag('crm.metric_provider');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }
}
