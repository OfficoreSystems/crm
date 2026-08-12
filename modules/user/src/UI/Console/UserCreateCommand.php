<?php

declare(strict_types=1);

namespace Crm\User\UI\Console;

use Crm\User\Application\CreateTeam;
use Crm\User\Application\CreateUser;
use Crm\User\Application\CreateUserCommand;
use Crm\User\Domain\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Es gibt bewusst keine Selbstregistrierung: Konten legt jemand an, der schon
 * Zugang hat. Fuer den allerersten Benutzer gibt es diesen Befehl.
 */
#[AsCommand(
    name: 'user:create',
    description: 'Legt einen Benutzer an.',
)]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly CreateTeam $createTeam,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-Mail-Adresse, dient als Anmeldename')
            ->addArgument('name', InputArgument::REQUIRED, 'Anzeigename')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Passwort. Ohne Angabe wird eines erzeugt und ausgegeben.')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Vergibt zusaetzlich ROLE_ADMIN')
            ->addOption('team', 't', InputOption::VALUE_REQUIRED, 'Teamname. Das Team wird angelegt, falls es fehlt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $password */
        $password = $input->getOption('password') ?? self::generatePassword();
        $generated = null === $input->getOption('password');

        /** @var string|null $teamName */
        $teamName = $input->getOption('team');
        $team = null !== $teamName ? ($this->createTeam)($teamName) : null;

        try {
            $user = ($this->createUser)(new CreateUserCommand(
                email: (string) $input->getArgument('email'),
                name: (string) $input->getArgument('name'),
                plainPassword: $password,
                roles: $input->getOption('admin') ? [Role::ADMIN] : [],
                teamId: $team?->id(),
            ));
        } catch (\DomainException|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Benutzer "%s" angelegt.', $user->email()));

        $rows = [
            ['Name', $user->name()],
            ['Rollen', implode(', ', $user->roles())],
            ['Team', $team?->name() ?? '—'],
        ];

        if ($generated) {
            // Nur hier ausgeben - danach existiert das Passwort nur noch als
            // Hash und ist nicht wiederherstellbar.
            $rows[] = ['Passwort', $password];
        }

        $io->table(['Feld', 'Wert'], $rows);

        if ($generated) {
            $io->warning('Das Passwort steht nur in dieser Ausgabe. Jetzt notieren.');
        }

        return Command::SUCCESS;
    }

    private static function generatePassword(): string
    {
        // 24 Zeichen aus zufaelligen Bytes - lang genug, dass die
        // Mindestlaenge nie zum Thema wird.
        return substr(rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '='), 0, 24);
    }
}
