<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\Infrastructure\SharedKernel;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityType;
use Crm\Activity\Infrastructure\SharedKernel\ActivityOwnership;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Wie {@see \Crm\Deal\Tests\Infrastructure\SharedKernel\DealOwnershipTest},
 * nur heisst der Besitzer hier Autor.
 */
final class ActivityOwnershipTest extends TestCase
{
    #[Test]
    public function it_answers_only_for_activities(): void
    {
        $ownership = new ActivityOwnership();

        self::assertSame('activity', $ownership->module());
        self::assertTrue($ownership->supports($this->activity()));
        self::assertFalse($ownership->supports(new \stdClass()));
    }

    #[Test]
    public function author_and_team_do_not_get_swapped(): void
    {
        $author = Uuid::v7();
        $team = Uuid::v7();

        $ownership = (new ActivityOwnership())->ownershipOf($this->activity($author, $team));

        self::assertSame($author->toString(), $ownership->ownerId);
        self::assertSame($team->toString(), $ownership->teamId);
    }

    #[Test]
    public function an_entry_without_an_author_reports_nothing(): void
    {
        // Etwa ein Eintrag, den ein Konsolenbefehl erzeugt hat.
        $ownership = (new ActivityOwnership())->ownershipOf($this->activity());

        self::assertNull($ownership->ownerId);
        self::assertNull($ownership->teamId);
    }

    private function activity(?Uuid $authorId = null, ?Uuid $authorTeamId = null): Activity
    {
        return Activity::log(
            type: ActivityType::NOTE,
            subject: new SubjectRef('contact', (string) Uuid::v7()),
            summary: 'Gespraechsnotiz',
            authorId: $authorId,
            authorTeamId: $authorTeamId,
        );
    }
}
