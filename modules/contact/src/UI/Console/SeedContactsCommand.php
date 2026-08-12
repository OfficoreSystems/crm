<?php

declare(strict_types=1);

namespace Crm\Contact\UI\Console;

use Crm\Contact\Application\CreateContact;
use Crm\Contact\Application\CreateContactCommand;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Beispieldaten, damit die Liste nach `make fresh` nicht leer ist.
 */
#[AsCommand(
    name: 'contact:seed',
    description: 'Legt Beispielkontakte an (idempotent: laeuft nur bei leerer Tabelle).',
)]
final class SeedContactsCommand extends Command
{
    /**
     * Die Firmennamen entsprechen denen aus company:seed. Aufgeloest wird
     * ueber den Finder - passt kein Name, bleibt der Kontakt firmenlos.
     */
    private const SAMPLES = [
        ['Anna', 'Berger', 'anna.berger@nordwind.example', 'Nordwind Logistik'],
        ['Bogdan', 'Petrov', 'b.petrov@heliosgruppe.example', 'Helios Gruppe'],
        ['Clara', 'Dupont', 'clara.dupont@atlasbau.example', 'Atlas Bau'],
        ['Deniz', 'Yilmaz', 'deniz.yilmaz@nordwind.example', 'Nordwind Logistik'],
        ['Erik', 'Lindqvist', null, null],
        ['Farida', 'Haddad', 'f.haddad@meridian.example', 'Meridian Consulting'],
        ['Grzegorz', 'Nowak', 'g.nowak@atlasbau.example', 'Atlas Bau'],
        ['Hanna', 'Vogel', 'hanna.vogel@heliosgruppe.example', null],
    ];

    public function __construct(
        private readonly CreateContact $createContact,
        private readonly ContactRepositoryInterface $contacts,
        private readonly CompanyFinderInterface $companies,
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

        $companyIds = $this->resolveCompanyIds();
        $linked = 0;

        foreach (self::SAMPLES as [$firstName, $lastName, $email, $companyName]) {
            $companyId = null === $companyName ? null : ($companyIds[$companyName] ?? null);

            if (null !== $companyId) {
                ++$linked;
            }

            ($this->createContact)(new CreateContactCommand($firstName, $lastName, $email, $companyId));
        }

        $io->success(sprintf('%d Beispielkontakte angelegt.', \count(self::SAMPLES)));

        if (0 === $linked) {
            // Kein Fehler: ohne company-Modul gibt es schlicht keine Firmen,
            // auf die sich verweisen liesse.
            $io->note('Keine Firma zugeordnet - ist das company-Modul installiert und geseedet?');
        } else {
            $io->note(sprintf('%d davon einer Firma zugeordnet.', $linked));
        }

        return Command::SUCCESS;
    }

    /**
     * Firmennamen einmal aufloesen statt je Kontakt.
     *
     * @return array<string, Uuid>
     */
    private function resolveCompanyIds(): array
    {
        $ids = [];

        foreach ($this->companies->findAll() as $company) {
            if (Uuid::isValid($company->id)) {
                $ids[$company->name] = Uuid::fromString($company->id);
            }
        }

        return $ids;
    }
}
