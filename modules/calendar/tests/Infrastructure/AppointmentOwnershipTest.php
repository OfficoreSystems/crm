<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Infrastructure;

use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\TimeSpan;
use Crm\Calendar\Infrastructure\SharedKernel\AppointmentOwnership;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AppointmentOwnershipTest extends TestCase
{
    #[Test]
    public function it_answers_only_for_appointments(): void
    {
        $ownership = new AppointmentOwnership();

        self::assertSame('calendar', $ownership->module());
        self::assertTrue($ownership->supports($this->appointment()));
        self::assertFalse($ownership->supports(new \stdClass()));
    }

    #[Test]
    public function owner_and_team_do_not_get_swapped(): void
    {
        $owner = Uuid::v7();
        $team = Uuid::v7();

        $ownership = (new AppointmentOwnership())->ownershipOf($this->appointment($owner, $team));

        self::assertSame($owner->toString(), $ownership->ownerId);
        self::assertSame($team->toString(), $ownership->teamId);
    }

    #[Test]
    public function an_appointment_without_an_owner_belongs_to_nobody(): void
    {
        $ownership = (new AppointmentOwnership())->ownershipOf($this->appointment());

        self::assertNull($ownership->ownerId);
        self::assertNull($ownership->teamId);
    }

    #[Test]
    public function it_declares_the_columns_the_visibility_filter_needs(): void
    {
        $columns = (new AppointmentOwnership())->restrictedColumns();

        self::assertSame(Appointment::class, $columns->entityClass);
        self::assertSame('owner_id', $columns->ownerColumn);
        self::assertSame('owner_team_id', $columns->teamColumn);
    }

    private function appointment(?Uuid $ownerId = null, ?Uuid $ownerTeamId = null): Appointment
    {
        return Appointment::schedule(
            title: 'Vor-Ort-Termin',
            when: TimeSpan::of(
                new \DateTimeImmutable('2026-08-20 10:00:00', new \DateTimeZone('UTC')),
                new \DateTimeImmutable('2026-08-20 11:00:00', new \DateTimeZone('UTC')),
            ),
            ownerId: $ownerId,
            ownerTeamId: $ownerTeamId,
        );
    }
}
