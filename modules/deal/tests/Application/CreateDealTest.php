<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Application;

use Crm\Deal\Application\CreateDeal;
use Crm\Deal\Application\CreateDealCommand;
use Crm\Deal\Application\MoveDealToStage;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\Deal\Tests\Double\InMemoryDealRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateDealTest extends TestCase
{
    #[Test]
    public function it_persists_the_new_deal(): void
    {
        $deals = new InMemoryDealRepository();

        $deal = (new CreateDeal($deals))(new CreateDealCommand(
            'Rahmenvertrag Seefracht',
            Money::fromDecimal('84000.00'),
        ));

        self::assertSame(1, $deals->countAll());
        self::assertSame($deal, $deals->find($deal->id()));
        self::assertSame('84000.00', $deal->value()->asDecimal());
    }

    #[Test]
    public function it_defaults_to_an_open_lead(): void
    {
        $deal = (new CreateDeal(new InMemoryDealRepository()))(new CreateDealCommand('Rahmenvertrag'));

        self::assertSame(Stage::LEAD, $deal->stage());
        self::assertTrue($deal->value()->isZero());
    }

    #[Test]
    public function it_accepts_references_without_checking_them(): void
    {
        // Wie bei contact: ohne die jeweiligen Module antworten die Finder
        // auf alles mit "kenne ich nicht". Eine Pflichtpruefung hier wuerde
        // jede Zuordnung unmoeglich machen.
        $deal = (new CreateDeal(new InMemoryDealRepository()))(new CreateDealCommand(
            'Rahmenvertrag',
            companyId: $company = Uuid::v7(),
            contactId: $contact = Uuid::v7(),
        ));

        self::assertTrue($company->equals($deal->companyId()));
        self::assertTrue($contact->equals($deal->contactId()));
    }

    #[Test]
    public function it_stores_nothing_when_the_title_is_blank(): void
    {
        $deals = new InMemoryDealRepository();

        try {
            (new CreateDeal($deals))(new CreateDealCommand('   '));
            self::fail('Ein leerer Titel haette abgelehnt werden muessen.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $deals->countAll());
        }
    }

    #[Test]
    public function moving_a_deal_persists_the_new_stage(): void
    {
        $deals = new InMemoryDealRepository();
        $deal = (new CreateDeal($deals))(new CreateDealCommand('Rahmenvertrag'));

        (new MoveDealToStage($deals))($deal, Stage::WON, $moment = new \DateTimeImmutable('2026-03-01'));

        $stored = $deals->find($deal->id());
        self::assertSame(Stage::WON, $stored?->stage());
        self::assertSame($moment, $stored?->closedAt());
    }

    #[Test]
    public function moving_back_to_an_open_stage_clears_the_closing_date(): void
    {
        $deals = new InMemoryDealRepository();
        $move = new MoveDealToStage($deals);
        $deal = (new CreateDeal($deals))(new CreateDealCommand('Rahmenvertrag'));

        $move($deal, Stage::LOST);
        $move($deal, Stage::PROPOSAL);

        self::assertNull($deals->find($deal->id())?->closedAt());
    }
}
