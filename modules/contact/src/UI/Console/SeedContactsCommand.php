<?php

declare(strict_types=1);

namespace Crm\Contact\UI\Console;

use Crm\Contact\Application\CreateContact;
use Crm\Contact\Application\CreateContactCommand;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Beispieldaten, damit die Liste nach `make fresh` nicht leer ist.
 */
#[AsCommand(
    name: 'contact:seed',
    description: 'Legt Beispielkontakte an (idempotent: laeuft nur bei leerer Tabelle).',
)]
final class SeedContactsCommand extends Command
{
    private const SAMPLES = [
        ['Anna', 'Berger', 'anna.berger@nordwind.example', 'Nordwind Logistik'],
        ['Bogdan', 'Petrov', 'b.petrov@heliosgruppe.example', 'Helios Gruppe'],
        ['Clara', 'Dupont', 'clara.dupont@atlasbau.example', 'Atlas Bau'],
        ['Deniz', 'Yilmaz', 'deniz.yilmaz@nordwind.example', 'Nordwind Logistik'],
        ['Erik', 'Lindqvist', null, 'Freiberuflich'],
        ['Farida', 'Haddad', 'f.haddad@meridian.example', 'Meridian Consulting'],
        ['Grzegorz', 'Nowak', 'g.nowak@atlasbau.example', 'Atlas Bau'],
        ['Hanna', 'Vogel', 'hanna.vogel@heliosgruppe.example', null],
    ];

    public function __construct(
        private readonly CreateContact $createContact,
        private readonly ContactRepositoryInterface $contacts,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->contacts->countAll() > 0) {
            $io->note('Es sind bereits Kontakte vorhanden - es wird nichts angelegt.');

            return Command::SUCCESS;
        }

        foreach (self::SAMPLES as [$firstName, $lastName, $email, $company]) {
            ($this->createContact)(new CreateContactCommand($firstName, $lastName, $email, $company));
        }

        $io->success(sprintf('%d Beispielkontakte angelegt.', \count(self::SAMPLES)));

        return Command::SUCCESS;
    }
}
