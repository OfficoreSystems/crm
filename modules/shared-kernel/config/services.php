<?php

declare(strict_types=1);

use Crm\SharedKernel\Menu\MenuRegistry;
use Crm\SharedKernel\Module\ModuleRegistry;
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
};
