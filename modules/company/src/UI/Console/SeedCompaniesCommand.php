<?php

declare(strict_types=1);

namespace Crm\Company\UI\Console;

use Crm\Company\Application\CreateCompany;
use Crm\Company\Application\CreateCompanyCommand;
use Crm\Company\Domain\Address;
use Crm\Company\Domain\CompanyRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'company:seed',
    description: 'Legt Beispielfirmen an (idempotent: laeuft nur bei leerer Tabelle).',
)]
final class SeedCompaniesCommand extends Command
{
    /**
     * Dieselben Firmennamen wie in contact:seed - so passen die
     * Beispieldaten der beiden Module zusammen.
     */
    private const SAMPLES = [
        ['Nordwind Logistik', 'Logistik', 'nordwind.example', 'Am Hafen 12', '20457', 'Hamburg', 'DE'],
        ['Helios Gruppe', 'Energie', 'heliosgruppe.example', 'Sonnenallee 3', '10435', 'Berlin', 'DE'],
        ['Atlas Bau', 'Bauwesen', 'atlasbau.example', 'Industriestrasse 88', '80339', 'Muenchen', 'DE'],
        ['Meridian Consulting', 'Beratung', 'meridian.example', 'Zeil 40', '60313', 'Frankfurt am Main', 'DE'],
        ['Talwind Energie', 'Energie', 'talwind.example', 'Rheinweg 5', '4051', 'Basel', 'CH'],
        ['Koralle Software', 'IT', 'koralle.example', 'Mariahilfer Strasse 20', '1070', 'Wien', 'AT'],
    ];

    public function __construct(
        private readonly CreateCompany $createCompany,
        private readonly CompanyRepositoryInterface $companies,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->companies->countAll() > 0) {
            $io->note('Es sind bereits Firmen vorhanden - es wird nichts angelegt.');

            return Command::SUCCESS;
        }

        foreach (self::SAMPLES as [$name, $industry, $domain, $street, $postalCode, $city, $country]) {
            ($this->createCompany)(new CreateCompanyCommand(
                name: $name,
                industry: $industry,
                website: $domain,
                email: 'info@'.$domain,
                address: new Address($street, $postalCode, $city, $country),
            ));
        }

        $io->success(sprintf('%d Beispielfirmen angelegt.', \count(self::SAMPLES)));

        return Command::SUCCESS;
    }
}
