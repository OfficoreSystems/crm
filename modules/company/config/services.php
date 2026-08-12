<?php

declare(strict_types=1);

use Crm\Company\Domain\CompanyRepositoryInterface;
use Crm\Company\Infrastructure\Doctrine\DoctrineCompanyRepository;
use Crm\Company\Infrastructure\SharedKernel\DoctrineCompanyFinder;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Crm\\Company\\', '../src/')
        ->exclude('../src/Domain/');

    $services->alias(CompanyRepositoryInterface::class, DoctrineCompanyRepository::class);

    // Ueberschreibt den NullCompanyFinder aus dem Shared Kernel.
    $services->alias(CompanyFinderInterface::class, DoctrineCompanyFinder::class);
};
