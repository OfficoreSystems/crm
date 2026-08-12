<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\UI;

use Crm\Deal\Application\CreateDeal;
use Crm\Deal\Domain\Stage;
use Crm\Deal\Tests\Double\InMemoryDealRepository;
use Crm\Deal\UI\Console\SeedDealsCommand;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;
use Crm\SharedKernel\Company\NullCompanyFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class SeedDealsCommandTest extends TestCase
{
    #[Test]
    public function it_seeds_an_empty_database(): void
    {
        [$tester, $deals] = $this->command();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(9, $deals->countAll());
    }

    #[Test]
    public function it_does_nothing_when_deals_already_exist(): void
    {
        [$tester, $deals] = $this->command();
        $tester->execute([]);

        $tester->execute([]);

        self::assertSame(9, $deals->countAll());
        self::assertStringContainsString('bereits Verkaufschancen vorhanden', $tester->getDisplay());
    }

    #[Test]
    public function the_samples_cover_open_and_closed_stages(): void
    {
        // Sonst waere die Gewinnquote auf dem Board immer null und man saehe
        // nicht, ob die Berechnung ueberhaupt funktioniert.
        [$tester, $deals] = $this->command();
        $tester->execute([]);

        self::assertGreaterThan(0, $deals->countByStage(Stage::WON));
        self::assertGreaterThan(0, $deals->countByStage(Stage::LOST));
        self::assertGreaterThan(0, $deals->countByStage(Stage::LEAD));
    }

    #[Test]
    public function it_links_deals_to_matching_companies(): void
    {
        $nordwind = Uuid::v7();
        $finder = new FakeCompanies([new CompanySummary((string) $nordwind, 'Nordwind Logistik')]);

        [$tester, $deals] = $this->command($finder);
        $tester->execute([]);

        $linked = array_filter(
            $deals->search(''),
            static fn ($d): bool => $nordwind->equals($d->companyId()),
        );

        self::assertCount(2, $linked, 'Zwei Beispielchancen gehoeren zu Nordwind.');
        self::assertStringContainsString('einer Firma zugeordnet', $tester->getDisplay());
    }

    #[Test]
    public function it_works_without_a_company_module_at_all(): void
    {
        [$tester, $deals] = $this->command(new NullCompanyFinder());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(9, $deals->countAll());
        self::assertStringContainsString('Keine Firma zugeordnet', $tester->getDisplay());
    }

    /**
     * @return array{0: CommandTester, 1: InMemoryDealRepository}
     */
    private function command(?CompanyFinderInterface $companies = null): array
    {
        $deals = new InMemoryDealRepository();

        $application = new Application();
        $application->addCommand(new SeedDealsCommand(
            new CreateDeal($deals),
            $deals,
            $companies ?? new FakeCompanies([]),
        ));

        return [new CommandTester($application->find('deal:seed')), $deals];
    }
}
