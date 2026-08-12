<?php

declare(strict_types=1);

namespace Crm\Company\Tests\UI;

use Crm\Company\Application\CreateCompany;
use Crm\Company\Tests\Double\InMemoryCompanyRepository;
use Crm\Company\UI\Console\SeedCompaniesCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCompaniesCommandTest extends TestCase
{
    #[Test]
    public function it_seeds_an_empty_database(): void
    {
        [$tester, $companies] = $this->command();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(6, $companies->countAll());
    }

    #[Test]
    public function it_does_nothing_when_companies_already_exist(): void
    {
        [$tester, $companies] = $this->command();
        $tester->execute([]);

        $tester->execute([]);

        self::assertSame(6, $companies->countAll());
        self::assertStringContainsString('bereits Firmen vorhanden', $tester->getDisplay());
    }

    #[Test]
    public function the_samples_carry_industry_and_address(): void
    {
        // Sie sind die Datenbasis fuer die Branchen-Schnellfilter und spaeter
        // fuer Auswertungen - ohne Branche waeren sie dafuer wertlos.
        [$tester, $companies] = $this->command();
        $tester->execute([]);

        $nordwind = $companies->findByName('Nordwind Logistik');

        self::assertNotNull($nordwind);
        self::assertSame('Logistik', $nordwind->industry());
        self::assertSame('Hamburg', $nordwind->address()->city);
        self::assertSame('DE', $nordwind->address()->country);
        self::assertSame('https://nordwind.example', $nordwind->website());
    }

    #[Test]
    public function the_samples_cover_more_than_one_country(): void
    {
        [$tester, $companies] = $this->command();
        $tester->execute([]);

        $countries = array_unique(array_filter(array_map(
            static fn ($c) => $c->address()->country,
            $companies->findAll(),
        )));

        self::assertGreaterThan(1, \count($countries));
    }

    /**
     * @return array{0: CommandTester, 1: InMemoryCompanyRepository}
     */
    private function command(): array
    {
        $companies = new InMemoryCompanyRepository();

        $application = new Application();
        $application->addCommand(new SeedCompaniesCommand(new CreateCompany($companies), $companies));

        return [new CommandTester($application->find('company:seed')), $companies];
    }
}
