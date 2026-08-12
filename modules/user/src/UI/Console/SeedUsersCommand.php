<?php

declare(strict_types=1);

namespace Crm\User\UI\Console;

use Crm\User\Application\CreateTeam;
use Crm\User\Application\CreateUser;
use Crm\User\Application\CreateUserCommand;
use Crm\User\Domain\Role;
use Crm\User\Domain\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Legt einen Anmelde-Benutzer fuer die lokale Entwicklung an.
 *
 * Verweigert die Arbeit in Produktion. Ein Konto mit fest verdrahtetem
 * Passwort ist genau so lange harmlos, wie es die Maschine des Entwicklers
 * nicht verlaesst.
 */
#[AsCommand(
    name: 'user:seed',
    description: 'Legt den Entwicklungs-Administrator an (nur dev/test).',
)]
final class SeedUsersCommand extends Command
{
    public const DEV_EMAIL = 'admin@officore.test';
    public const DEV_PASSWORD = 'officore-dev-passwort';
    public const DEV_TEAM = 'Vertrieb';

    public function __construct(
        private readonly CreateUser $createUser,
        private readonly CreateTeam $createTeam,
        private readonly UserRepositoryInterface $users,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\in_array($this->environment, ['dev', 'test'], true)) {
            $io->error(sprintf(
                'user:seed laeuft nur in dev und test, nicht in "%s". Nutze user:create.',
                $this->environment,
            ));

            return Command::FAILURE;
        }

        if ($this->users->countAll() > 0) {
            $io->note('Es sind bereits Benutzer vorhanden - es wird nichts angelegt.');

            return Command::SUCCESS;
        }

        $team = ($this->createTeam)(self::DEV_TEAM);

        ($this->createUser)(new CreateUserCommand(
            email: self::DEV_EMAIL,
            name: 'Entwicklungs-Administrator',
            plainPassword: self::DEV_PASSWORD,
            roles: [Role::ADMIN],
            teamId: $team->id(),
        ));

        $io->success('Entwicklungs-Administrator angelegt.');
        $io->table(['Feld', 'Wert'], [
            ['E-Mail', self::DEV_EMAIL],
            ['Passwort', self::DEV_PASSWORD],
            ['Team', $team->name()],
        ]);

        return Command::SUCCESS;
    }
}
