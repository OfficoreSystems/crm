<?php

declare(strict_types=1);

namespace Crm\Deal\UI\Console;

use Crm\Deal\Application\CreateDeal;
use Crm\Deal\Application\CreateDealCommand;
use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'deal:seed',
    description: 'Legt Beispiel-Verkaufschancen an (idempotent: laeuft nur bei leerer Tabelle).',
)]
final class SeedDealsCommand extends Command
{
    /**
     * Titel, Wert in Euro, Stufe, Firmenname. Der Firmenname wird ueber den
     * Finder aufgeloest - fehlt das company-Modul, bleibt die Chance ohne
     * Firma.
     */
    private const SAMPLES = [
        ['Rahmenvertrag Seefracht', '84000.00', Stage::NEGOTIATION, 'Nordwind Logistik'],
        ['Zusatzmodul Tracking', '12500.00', Stage::PROPOSAL, 'Nordwind Logistik'],
        ['Photovoltaik Dachflaeche', '156000.00', Stage::QUALIFIED, 'Helios Gruppe'],
        ['Wartungsvertrag 2027', '23400.00', Stage::WON, 'Helios Gruppe'],
        ['Neubau Buerokomplex', '412000.00', Stage::LEAD, 'Atlas Bau'],
        ['Sanierung Lagerhalle', '67800.00', Stage::LOST, 'Atlas Bau'],
        ['Strategieberatung Q1', '45000.00', Stage::PROPOSAL, 'Meridian Consulting'],
        ['Pilotprojekt Datenmigration', '18900.00', Stage::QUALIFIED, 'Koralle Software'],
        ['Ausschreibung Windpark', '890000.00', Stage::LEAD, 'Talwind Energie'],
    ];

    public function __construct(
        private readonly CreateDeal $createDeal,
        private readonly DealRepositoryInterface $deals,
        private readonly CompanyFinderInterface $companies,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->deals->countAll() > 0) {
            $io->note('Es sind bereits Verkaufschancen vorhanden - es wird nichts angelegt.');

            return Command::SUCCESS;
        }

        $companyIds = $this->resolveCompanyIds();
        $linked = 0;

        foreach (self::SAMPLES as [$title, $value, $stage, $companyName]) {
            $companyId = $companyIds[$companyName] ?? null;

            if (null !== $companyId) {
                ++$linked;
            }

            ($this->createDeal)(new CreateDealCommand(
                title: $title,
                value: Money::fromDecimal($value),
                stage: $stage,
                companyId: $companyId,
            ));
        }

        $io->success(sprintf('%d Beispielchancen angelegt.', \count(self::SAMPLES)));
        $io->note(0 === $linked
            ? 'Keine Firma zugeordnet - ist das company-Modul installiert und geseedet?'
            : sprintf('%d davon einer Firma zugeordnet.', $linked));

        return Command::SUCCESS;
    }

    /**
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
