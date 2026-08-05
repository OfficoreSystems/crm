<?php

declare(strict_types=1);

use Crm\Contact\Domain\ContactRepositoryInterface;
use Crm\Contact\Infrastructure\Doctrine\DoctrineContactRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Crm\\Contact\\', '../src/')
        // Entities sind keine Services. Das Interface im selben Verzeichnis
        // wuerde ohnehin uebersprungen, aber der Ausschluss macht es explizit.
        ->exclude('../src/Domain/');

    // Der Port zeigt auf die Doctrine-Implementierung. Einziger Ort, an dem
    // Application und Domain mit Infrastructure verbunden werden.
    $services->alias(ContactRepositoryInterface::class, DoctrineContactRepository::class);
};
