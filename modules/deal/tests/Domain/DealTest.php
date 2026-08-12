<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Domain;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class DealTest extends TestCase
{
    #[Test]
    public function it_starts_as_an_open_lead_worth_nothing(): void
    {
        $deal = Deal::create('Rahmenvertrag Seefracht');

        self::assertSame(Stage::LEAD, $deal->stage());
        self::assertTrue($deal->isOpen());
        self::assertTrue($deal->value()->isZero());
        self::assertNull($deal->closedAt());
        self::assertInstanceOf(UuidV7::class, $deal->id());
    }

    #[Test]
    public function it_trims_the_title(): void
    {
        self::assertSame('Rahmenvertrag', Deal::create('  Rahmenvertrag  ')->title());
    }

    #[Test]
    public function it_rejects_a_blank_title(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Deal::create('   ');
    }

    #[Test]
    public function it_rejects_a_negative_value(): void
    {
        // Ein negativer Wert waere kein Geschaeft, sondern ein
        // Vorzeichenfehler beim Import - und wuerde die Pipeline-Summe still
        // verfaelschen.
        $this->expectException(\InvalidArgumentException::class);

        Deal::create('Rahmenvertrag', Money::fromCents(-1));
    }

    #[Test]
    public function changing_to_a_negative_value_is_refused(): void
    {
        $deal = Deal::create('Rahmenvertrag', Money::fromCents(1000));

        $this->expectException(\InvalidArgumentException::class);

        $deal->changeValue(Money::fromCents(-1));
    }

    #[Test]
    public function closing_it_records_when(): void
    {
        $deal = Deal::create('Rahmenvertrag');
        $moment = new \DateTimeImmutable('2026-03-01 09:15:00');

        $deal->moveTo(Stage::WON, $moment);

        self::assertTrue($deal->isWon());
        self::assertFalse($deal->isOpen());
        self::assertSame($moment, $deal->closedAt());
    }

    #[Test]
    public function losing_it_also_records_when(): void
    {
        $deal = Deal::create('Rahmenvertrag');

        $deal->moveTo(Stage::LOST, $moment = new \DateTimeImmutable('2026-03-01 09:15:00'));

        self::assertFalse($deal->isOpen());
        self::assertFalse($deal->isWon());
        self::assertSame($moment, $deal->closedAt());
    }

    #[Test]
    public function reopening_it_clears_the_closing_date(): void
    {
        // Im Vertrieb wird Verlorenes wieder aufgemacht. Bliebe closedAt
        // stehen, waere jede Auswertung "abgeschlossen im Maerz" falsch.
        $deal = Deal::create('Rahmenvertrag');
        $deal->moveTo(Stage::LOST);

        $deal->moveTo(Stage::NEGOTIATION);

        self::assertTrue($deal->isOpen());
        self::assertNull($deal->closedAt());
    }

    #[Test]
    public function moving_to_the_same_stage_changes_nothing(): void
    {
        $deal = Deal::create('Rahmenvertrag');
        $deal->moveTo(Stage::WON, $first = new \DateTimeImmutable('2026-03-01 09:15:00'));

        $deal->moveTo(Stage::WON, new \DateTimeImmutable('2026-04-01 10:00:00'));

        self::assertSame($first, $deal->closedAt(), 'Das Abschlussdatum darf sich nicht verschieben.');
    }

    #[Test]
    public function it_can_be_created_directly_in_a_closed_stage(): void
    {
        $deal = Deal::create('Altvertrag', stage: Stage::WON, createdAt: $moment = new \DateTimeImmutable('2026-01-01'));

        self::assertSame($moment, $deal->closedAt());
    }

    #[Test]
    public function stages_can_be_skipped_and_walked_back(): void
    {
        // Bewusst keine Zustandsmaschine: im Vertrieb springt man zurueck und
        // ueberspringt Stufen. Eine Beschraenkung waere umgangen worden.
        $deal = Deal::create('Rahmenvertrag');

        $deal->moveTo(Stage::NEGOTIATION);
        self::assertSame(Stage::NEGOTIATION, $deal->stage());

        $deal->moveTo(Stage::LEAD);
        self::assertSame(Stage::LEAD, $deal->stage());
    }

    #[Test]
    public function it_holds_references_to_other_modules_as_scalar_ids(): void
    {
        $company = Uuid::v7();
        $contact = Uuid::v7();
        $owner = Uuid::v7();

        $deal = Deal::create('Rahmenvertrag', companyId: $company, contactId: $contact, ownerId: $owner);

        self::assertTrue($company->equals($deal->companyId()));
        self::assertTrue($contact->equals($deal->contactId()));
        self::assertTrue($owner->equals($deal->ownerId()));
    }

    #[Test]
    public function the_references_can_be_changed_and_cleared(): void
    {
        $deal = Deal::create('Rahmenvertrag', companyId: Uuid::v7());

        $deal->assignToCompany(null);
        $deal->assignToContact($contact = Uuid::v7());
        $deal->assignToOwner(null);

        self::assertNull($deal->companyId());
        self::assertTrue($contact->equals($deal->contactId()));
        self::assertNull($deal->ownerId());
    }

    #[Test]
    public function it_can_be_renamed_and_revalued_and_rescheduled(): void
    {
        $deal = Deal::create('Rahmenvertrag');

        $deal->rename(' Rahmenvertrag Seefracht ');
        $deal->changeValue(Money::fromDecimal('84000.00'));
        $deal->expectToCloseOn($date = new \DateTimeImmutable('2026-06-30'));

        self::assertSame('Rahmenvertrag Seefracht', $deal->title());
        self::assertSame('84000.00', $deal->value()->asDecimal());
        self::assertSame($date, $deal->expectedCloseDate());
    }
}
