<?php

declare(strict_types=1);

use Crm\SharedKernel\User\UserFinderInterface;
use Crm\User\Domain\PasswordHasherInterface;
use Crm\User\Domain\TeamRepositoryInterface;
use Crm\User\Domain\UserRepositoryInterface;
use Crm\User\Infrastructure\Doctrine\DoctrineTeamRepository;
use Crm\User\Infrastructure\Doctrine\DoctrineUserRepository;
use Crm\User\Infrastructure\Security\DomainUserProvider;
use Crm\User\Infrastructure\Security\SymfonyPasswordHasher;
use Crm\User\Infrastructure\SharedKernel\DoctrineUserFinder;
use Crm\User\UI\Console\SeedUsersCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Crm\\User\\', '../src/')
        ->exclude('../src/Domain/');

    $services->alias(UserRepositoryInterface::class, DoctrineUserRepository::class);
    $services->alias(TeamRepositoryInterface::class, DoctrineTeamRepository::class);
    $services->alias(PasswordHasherInterface::class, SymfonyPasswordHasher::class);

    // Ueberschreiben die Vorgaben aus dem Shared Kernel. Funktioniert, weil
    // dieses Bundle in config/bundles.php hinter dem shared-kernel steht und
    // die spaetere Definition gewinnt.
    $services->alias(UserFinderInterface::class, DoctrineUserFinder::class);
    $services->alias('crm.security.user_provider', DomainUserProvider::class);

    // Der Seed-Befehl muss wissen, ob er laufen darf. Autowiring kann einen
    // string nicht aufloesen, also explizit.
    $services->set(SeedUsersCommand::class)
        ->arg('$environment', param('kernel.environment'));
};
