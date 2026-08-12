<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Kein Repository-Alias: dieses Modul hat keine Persistenz.
    $services->load('Crm\\Search\\', '../src/')
        ->exclude('../src/Domain/');
};
