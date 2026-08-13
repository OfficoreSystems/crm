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

    public const DEV_SALES_EMAIL = 'vertrieb@officore.test';
    public const DEV_BACKOFFICE_EMAIL = 'innendienst@officore.test';
    public const DEV_BACKOFFICE_TEAM = 'Innendienst';

    /**
     * Drei Konten, nicht eines.
     *
     * Mit einem einzigen Administrator laesst sich nicht erkennen, ob die
     * Rechte ueberhaupt greifen - er darf ohnehin alles. Erst ein zweites
     * Team macht sichtbar, dass der Doctrine-Filter fremde Chancen gar nicht
     * erst laedt.
     *
     * @var list<array{string, string, bool, string}> E-Mail, Name, Admin, Team
     */
    private const ACCOUNTS = [
        [self::DEV_EMAIL, 'Entwicklungs-Administrator', true, self::DEV_TEAM],
        [self::DEV_SALES_EMAIL, 'Vera Vertrieb', false, self::DEV_TEAM],
        [self::DEV_BACKOFFICE_EMAIL, 'Ingo Innendienst', false, self::DEV_BACKOFFICE_TEAM],
    ];

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

        $rows = [];

        foreach (self::ACCOUNTS as [$email, $name, $isAdmin, $teamName]) {
            $team = ($this->createTeam)($teamName);

            ($this->createUser)(new CreateUserCommand(
                email: $email,
                name: $name,
                plainPassword: self::DEV_PASSWORD,
                roles: $isAdmin ? [Role::ADMIN] : [],
                teamId: $team->id(),
            ));

            $rows[] = [$email, $isAdmin ? 'Administrator' : 'Benutzer', $team->name()];
        }

        $io->success(sprintf('%d Entwicklungskonten angelegt.', \count(self::ACCOUNTS)));
        $io->table(['E-Mail', 'Rolle', 'Team'], $rows);
        $io->note(sprintf('Passwort fuer alle: %s', self::DEV_PASSWORD));

        return Command::SUCCESS;
    }
}
