<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Infrastructure\SharedKernel;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\Deal\Infrastructure\SharedKernel\DealOwnership;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Die Uebersetzung, mit der der Voter arbeitet.
 *
 * Klein, aber sicherheitsrelevant: verwechselt sie Besitzer und Team, faellt
 * das nirgends auf - die Anwendung zeigt weiter Daten, nur den falschen
 * Leuten.
 */
final class DealOwnershipTest extends TestCase
{
    #[Test]
    public function it_answers_only_for_deals(): void
    {
        $ownership = new DealOwnership();

        self::assertSame('deal', $ownership->module());
        self::assertTrue($ownership->supports($this->deal()));
        self::assertFalse($ownership->supports(new \stdClass()));
    }

    #[Test]
    public function owner_and_team_do_not_get_swapped(): void
    {
        $owner = Uuid::v7();
        $team = Uuid::v7();

        $ownership = (new DealOwnership())->ownershipOf($this->deal($owner, $team));

        self::assertSame($owner->toString(), $ownership->ownerId);
        self::assertSame($team->toString(), $ownership->teamId);
    }

    #[Test]
    public function a_deal_nobody_owns_reports_nothing_instead_of_an_empty_string(): void
    {
        // Der Unterschied ist nicht kosmetisch: '' waere ein Wert, mit dem
        // sich vergleichen laesst - und zwei besitzerlose Chancen gehoerten
        // damit demselben Niemand.
        $ownership = (new DealOwnership())->ownershipOf($this->deal());

        self::assertNull($ownership->ownerId);
        self::assertNull($ownership->teamId);
    }

    private function deal(?Uuid $ownerId = null, ?Uuid $ownerTeamId = null): Deal
    {
        return Deal::create(
            title: 'Rahmenvertrag Seefracht',
            value: Money::fromDecimal('84000.00'),
            stage: Stage::NEGOTIATION,
            ownerId: $ownerId,
            ownerTeamId: $ownerTeamId,
        );
    }
}
