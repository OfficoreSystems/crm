<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\UI;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\Deal\Tests\Double\InMemoryDealRepository;
use Crm\Deal\UI\Component\PipelineBoard;
use Crm\SharedKernel\Company\CompanySummary;
use Crm\SharedKernel\Company\NullCompanyFinder;
use Crm\SharedKernel\Contact\ContactSummary;
use Crm\SharedKernel\Contact\NullContactFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PipelineBoardTest extends TestCase
{
    #[Test]
    public function it_groups_deals_into_their_stages(): void
    {
        $board = $this->board([
            Deal::create('A', Money::fromCents(100), Stage::LEAD),
            Deal::create('B', Money::fromCents(200), Stage::LEAD),
            Deal::create('C', Money::fromCents(300), Stage::WON),
        ]);

        self::assertCount(2, $board->dealsIn(Stage::LEAD));
        self::assertCount(1, $board->dealsIn(Stage::WON));
        self::assertCount(0, $board->dealsIn(Stage::PROPOSAL));
    }

    #[Test]
    public function it_shows_every_stage_even_when_empty(): void
    {
        // Eine leere Spalte ist eine Information: dort fehlt etwas.
        self::assertCount(6, $this->board([])->getStages());
    }

    #[Test]
    public function it_sums_each_column(): void
    {
        $board = $this->board([
            Deal::create('A', Money::fromDecimal('100.50'), Stage::LEAD),
            Deal::create('B', Money::fromDecimal('200.25'), Stage::LEAD),
        ]);

        self::assertSame('300.75', $board->valueIn(Stage::LEAD)->asDecimal());
        self::assertTrue($board->valueIn(Stage::WON)->isZero());
    }

    #[Test]
    public function the_open_value_excludes_closed_deals(): void
    {
        // Die Zahl, nach der im Vertrieb tatsaechlich gefragt wird.
        $board = $this->board([
            Deal::create('offen', Money::fromDecimal('1000.00'), Stage::NEGOTIATION),
            Deal::create('gewonnen', Money::fromDecimal('5000.00'), Stage::WON),
            Deal::create('verloren', Money::fromDecimal('9000.00'), Stage::LOST),
        ]);

        self::assertSame('1000.00', $board->getOpenValue()->asDecimal());
    }

    #[Test]
    public function the_win_rate_counts_only_closed_deals(): void
    {
        $board = $this->board([
            Deal::create('offen', stage: Stage::LEAD),
            Deal::create('gewonnen', stage: Stage::WON),
            Deal::create('verloren', stage: Stage::LOST),
        ]);

        self::assertSame(50.0, $board->getWinRate());
    }

    #[Test]
    public function the_win_rate_is_null_when_nothing_is_closed(): void
    {
        // Null ist etwas anderes als null Prozent - das Template
        // unterscheidet beides.
        self::assertNull($this->board([Deal::create('offen', stage: Stage::LEAD)])->getWinRate());
        self::assertNull($this->board([])->getWinRate());
    }

    #[Test]
    public function it_resolves_company_and_contact_names(): void
    {
        $companyId = Uuid::v7();
        $contactId = Uuid::v7();
        $deal = Deal::create('Rahmenvertrag', companyId: $companyId, contactId: $contactId);

        $companies = new FakeCompanies([new CompanySummary((string) $companyId, 'Nordwind Logistik')]);
        $contacts = new FakeContacts([new ContactSummary((string) $contactId, 'Anna Berger')]);

        $board = $this->board([$deal], $companies, $contacts);

        self::assertSame('Nordwind Logistik', $board->companyNameFor($deal));
        self::assertSame('Anna Berger', $board->contactNameFor($deal));
    }

    #[Test]
    public function unresolvable_references_render_as_null(): void
    {
        // Der Normalfall ohne installierte Module: die Null-Finder aus dem
        // Shared Kernel antworten auf alles mit "kenne ich nicht".
        $deal = Deal::create('Rahmenvertrag', companyId: Uuid::v7(), contactId: Uuid::v7());

        $board = $this->board([$deal]);

        self::assertNull($board->companyNameFor($deal));
        self::assertNull($board->contactNameFor($deal));
    }

    #[Test]
    public function a_deal_without_references_renders_as_null(): void
    {
        $deal = Deal::create('Rahmenvertrag');

        $board = $this->board([$deal]);

        self::assertNull($board->companyNameFor($deal));
        self::assertNull($board->contactNameFor($deal));
    }

    #[Test]
    public function it_resolves_each_kind_of_reference_in_a_single_lookup(): void
    {
        // Zwei Modulgrenzen, zwei Aufrufe - nicht zwei pro Karte.
        $companyId = Uuid::v7();
        $companies = new FakeCompanies([new CompanySummary((string) $companyId, 'Nordwind')]);
        $contacts = new FakeContacts([]);

        $deals = [
            Deal::create('A', companyId: $companyId),
            Deal::create('B', companyId: $companyId),
            Deal::create('C', companyId: $companyId),
        ];
        $board = $this->board($deals, $companies, $contacts);

        foreach ($deals as $deal) {
            $board->companyNameFor($deal);
        }

        self::assertSame(1, $companies->findManyCalls);
    }

    #[Test]
    public function an_empty_query_does_not_count_as_filtered(): void
    {
        $board = $this->board([]);
        $board->query = '  ';

        self::assertFalse($board->isFiltered());
    }

    /**
     * @param list<Deal> $deals
     */
    private function board(array $deals, ?FakeCompanies $companies = null, ?FakeContacts $contacts = null): PipelineBoard
    {
        $repository = new InMemoryDealRepository();

        foreach ($deals as $deal) {
            $repository->save($deal);
        }

        return new PipelineBoard(
            $repository,
            $companies ?? new NullCompanyFinder(),
            $contacts ?? new NullContactFinder(),
        );
    }
}
