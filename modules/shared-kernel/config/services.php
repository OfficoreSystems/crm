<?php

declare(strict_types=1);

use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\NullCompanyFinder;
use Crm\SharedKernel\Contact\ContactFinderInterface;
use Crm\SharedKernel\Contact\NullContactFinder;
use Crm\SharedKernel\Menu\MenuRegistry;
use Crm\SharedKernel\Module\ModuleRegistry;
use Crm\SharedKernel\Security\NullUserProvider;
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
