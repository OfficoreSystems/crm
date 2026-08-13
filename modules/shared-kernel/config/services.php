<?php

declare(strict_types=1);

use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\NullCompanyFinder;
use Crm\SharedKernel\Dashboard\MetricRegistry;
use Crm\SharedKernel\Contact\ContactFinderInterface;
use Crm\SharedKernel\Contact\NullContactFinder;
use Crm\SharedKernel\Menu\MenuRegistry;
use Crm\SharedKernel\Module\ModuleRegistry;
use Crm\SharedKernel\Security\CrmVoter;
use Crm\SharedKernel\Security\NullUserProvider;
use Crm\SharedKernel\Security\OwnershipRegistry;
use Crm\SharedKernel\Security\PermissionMatrix;
use Crm\SharedKernel\Infrastructure\Localization\ActorLocaleListener;
use Crm\SharedKernel\Infrastructure\Security\RecordVisibilityConfigurator;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use Crm\SharedKernel\User\NullUserFinder;
use Crm\SharedKernel\User\UserFinderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(MenuRegistry::class)
        ->args([tagged_iterator('crm.menu_provider')]);

    $services->set(ModuleRegistry::class)
        ->args([tagged_iterator('crm.module')]);

    $services->set(SubjectResolverRegistry::class)
        ->args([tagged_iterator('crm.subject_resolver')]);

    $services->set(MetricRegistry::class)
        ->args([tagged_iterator('crm.metric_provider')]);

    $services->set(OwnershipRegistry::class)
        ->args([tagged_iterator('crm.record_ownership')]);

    // Die Rechtematrix als Vorgabe. Wer sie anpassen will, definiert diesen
    // Dienst in der Anwendung neu - dann gewinnt die spaetere Definition.
    $services->set(PermissionMatrix::class)
        ->factory([PermissionMatrix::class, 'default']);

    $services->set(CrmVoter::class)
        ->tag('security.voter');

    // Der Doctrine-Filter selbst wird von der Bundle-Klasse konfiguriert, aber
    // Doctrine baut ihn ohne Container. Diese Klasse fuettert ihn pro Request
    // mit Benutzer und Scope - ohne sie ist der Filter dauerhaft aus, und das
    // faellt nicht auf: die Seiten funktionieren, sie zeigen nur zu viel.
    $services->set(RecordVisibilityConfigurator::class);

    // Dieselbe Bauart wie oben, derselbe Grund: das Attribut #[AsEventListener]
    // wirkt nur an einer Klasse, die auch ein Dienst ist.
    $services->set(ActorLocaleListener::class);

    // Standardimplementierung. Das user-Modul ueberschreibt diesen Alias mit
    // seiner Doctrine-Variante - es steht in config/bundles.php hinter dem
    // shared-kernel, und die spaetere Definition gewinnt.
    //
    // Ohne diesen Vorgabewert koennte kein Modul UserFinderInterface
    // injizieren, ohne das user-Modul zur Pflicht zu machen.
    $services->set(NullUserFinder::class);
    $services->alias(UserFinderInterface::class, NullUserFinder::class);

    $services->set(NullCompanyFinder::class);
    $services->alias(CompanyFinderInterface::class, NullCompanyFinder::class);

    $services->set(NullContactFinder::class);
    $services->alias(ContactFinderInterface::class, NullContactFinder::class);

    // Feste Service-ID, auf die config/packages/security.yaml verweist.
    // Der Core kommt so ohne Modulnamen aus; das user-Modul haengt hier seine
    // eigene Implementierung ein. Siehe NullUserProvider fuer den Grund,
    // warum die Firewall nicht aus dem Modul heraus konfigurierbar ist.
    $services->set(NullUserProvider::class);
    $services->alias('crm.security.user_provider', NullUserProvider::class);
};
