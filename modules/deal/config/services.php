<?php

declare(strict_types=1);

use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Infrastructure\Doctrine\DoctrineDealRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Crm\\Deal\\', '../src/')
        ->exclude('../src/Domain/');

    $services->alias(DealRepositoryInterface::class, DoctrineDealRepository::class);
};
