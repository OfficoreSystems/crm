<?php

declare(strict_types=1);

use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\Calendar\Domain\CalendarFeedRepositoryInterface;
use Crm\Calendar\Infrastructure\Doctrine\DoctrineAppointmentRepository;
use Crm\Calendar\Infrastructure\Doctrine\DoctrineCalendarFeedRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Crm\\Calendar\\', '../src/')
        ->exclude('../src/Domain/');

    $services->alias(AppointmentRepositoryInterface::class, DoctrineAppointmentRepository::class);
    $services->alias(CalendarFeedRepositoryInterface::class, DoctrineCalendarFeedRepository::class);
};
