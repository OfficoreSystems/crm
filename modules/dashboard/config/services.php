<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Kein Domain-Ausschluss noetig: dieses Modul hat keine Domain-Schicht.
    $services->load('Crm\\Dashboard\\', '../src/');
};
