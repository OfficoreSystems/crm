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

    // The permission matrix as a default. To adjust it, redefine this service
    // in the application - the later definition then wins.
    $services->set(PermissionMatrix::class)
        ->factory([PermissionMatrix::class, 'default']);

    $services->set(CrmVoter::class)
        ->tag('security.voter');

    // The Doctrine filter itself is configured by the bundle class, but
    // Doctrine builds it without a container. This class feeds it user and scope
    // per request - without it the filter is off permanently, and that does not
    // show: the pages work, they just display too much.
    $services->set(RecordVisibilityConfigurator::class);

    // Same construction as above, same reason: the #[AsEventListener] attribute
    // only takes effect on a class that is also a service.
    $services->set(ActorLocaleListener::class);

    // Default implementation. The user module overrides this alias with its
    // Doctrine variant - it sits behind the shared kernel in config/bundles.php,
    // and the later definition wins.
    //
    // Without this default no module could inject UserFinderInterface without
    // making the user module mandatory.
    $services->set(NullUserFinder::class);
    $services->alias(UserFinderInterface::class, NullUserFinder::class);

    $services->set(NullCompanyFinder::class);
    $services->alias(CompanyFinderInterface::class, NullCompanyFinder::class);

    $services->set(NullContactFinder::class);
    $services->alias(ContactFinderInterface::class, NullContactFinder::class);

    // Fixed service ID that config/packages/security.yaml points at. The core
    // thereby manages without module names; the user module hooks its own
    // implementation in here. See NullUserProvider for why the firewall cannot
    // be configured from the module.
    $services->set(NullUserProvider::class);
    $services->alias('crm.security.user_provider', NullUserProvider::class);
};
