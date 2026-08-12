<?php

declare(strict_types=1);

use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\Activity\Infrastructure\Doctrine\DoctrineActivityRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Crm\\Activity\\', '../src/')
        ->exclude('../src/Domain/');

    $services->alias(ActivityRepositoryInterface::class, DoctrineActivityRepository::class);
};
