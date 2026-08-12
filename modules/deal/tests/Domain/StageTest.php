<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Domain;

use Crm\Deal\Domain\Stage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StageTest extends TestCase
{
    #[Test]
    public function only_won_and_lost_are_closed(): void
    {
        self::assertTrue(Stage::WON->isClosed());
        self::assertTrue(Stage::LOST->isClosed());

        foreach ([Stage::LEAD, Stage::QUALIFIED, Stage::PROPOSAL, Stage::NEGOTIATION] as $stage) {
            self::assertTrue($stage->isOpen(), $stage->value.' sollte offen sein');
        }
    }

    #[Test]
    public function only_won_counts_as_won(): void
    {
        self::assertTrue(Stage::WON->isWon());
        self::assertFalse(Stage::LOST->isWon());
        self::assertFalse(Stage::NEGOTIATION->isWon());
    }

    #[Test]
    public function the_initial_stage_is_lead(): void
    {
        self::assertSame(Stage::LEAD, Stage::initial());
        self::assertTrue(Stage::initial()->isOpen());
    }

    #[Test]
    public function every_stage_has_a_label_and_a_unique_position(): void
    {
        $positions = [];

        foreach (Stage::cases() as $stage) {
            self::assertNotSame('', $stage->label());
            $positions[] = $stage->position();
        }

        self::assertSame($positions, array_unique($positions), 'Positionen muessen eindeutig sein');
    }

    #[Test]
    public function ordered_puts_the_closed_stages_last(): void
    {
        $ordered = Stage::ordered();

        self::assertSame(Stage::LEAD, $ordered[0]);
        self::assertSame(Stage::WON, $ordered[4]);
        self::assertSame(Stage::LOST, $ordered[5]);
    }

    #[Test]
    public function open_stages_exclude_the_end_states(): void
    {
        $open = Stage::openStages();

        self::assertCount(4, $open);
        self::assertNotContains(Stage::WON, $open);
        self::assertNotContains(Stage::LOST, $open);
    }
}
