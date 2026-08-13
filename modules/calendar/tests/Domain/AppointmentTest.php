<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Domain;

use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\TimeSpan;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AppointmentTest extends TestCase
{
    #[Test]
    public function it_keeps_what_it_was_given(): void
    {
        $owner = Uuid::v7();
        $team = Uuid::v7();

        $appointment = Appointment::schedule(
            title: 'Vor-Ort-Termin',
            when: $this->span(),
            description: 'Vorstellung des Leistungsumfangs',
            location: 'Hamburg',
            subject: new SubjectRef('contact', 'kontakt-1'),
            ownerId: $owner,
            ownerTeamId: $team,
        );

        self::assertSame('Vor-Ort-Termin', $appointment->title());
        self::assertSame('Hamburg', $appointment->location());
        self::assertSame('contact', $appointment->subject()?->type);
        self::assertTrue($owner->equals($appointment->ownerId()));
        self::assertTrue($team->equals($appointment->ownerTeamId()));
    }

    #[Test]
    public function an_appointment_needs_no_subject(): void
    {
        // Ein Teammeeting gehoert zu keinem Datensatz im CRM.
        self::assertNull($this->appointment()->subject());
    }

    #[Test]
    public function a_title_of_only_spaces_is_refused(): void
    {
        $this->expectExceptionMessage('without a title');

        Appointment::schedule(title: '   ', when: $this->span());
    }

    #[Test]
    public function empty_optional_fields_become_null_instead_of_empty_strings(): void
    {
        // Sonst zeigt die Oberflaeche eine leere Zeile statt gar keiner - und
        // im ICS stuende "LOCATION:" ohne Wert.
        $appointment = Appointment::schedule(
            title: 'Termin',
            when: $this->span(),
            description: '  ',
            location: '',
        );

        self::assertNull($appointment->description());
        self::assertNull($appointment->location());
    }

    #[Test]
    public function a_new_appointment_starts_at_sequence_zero(): void
    {
        self::assertSame(0, $this->appointment()->sequence());
    }

    #[Test]
    public function rescheduling_raises_the_sequence(): void
    {
        // Ohne steigende SEQUENCE ignorieren viele Clients die Aktualisierung.
        $appointment = $this->appointment();

        $appointment->reschedule(TimeSpan::of(
            new \DateTimeImmutable('2026-09-01 10:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2026-09-01 11:00:00', new \DateTimeZone('UTC')),
        ));

        self::assertSame(1, $appointment->sequence());
        self::assertSame('2026-09-01 10:00:00', $appointment->startsAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function rescheduling_to_the_same_time_changes_nothing(): void
    {
        // Sonst blinken die Termine in fremden Kalendern bei jedem Speichern
        // auf - die Clients halten jede neue SEQUENCE fuer eine Aenderung.
        $appointment = $this->appointment();

        $appointment->reschedule($this->span());

        self::assertSame(0, $appointment->sequence());
    }

    #[Test]
    public function renaming_raises_the_sequence(): void
    {
        $appointment = $this->appointment();

        $appointment->rename('Anderer Titel');

        self::assertSame('Anderer Titel', $appointment->title());
        self::assertSame(1, $appointment->sequence());
    }

    #[Test]
    public function an_all_day_appointment_stays_an_all_day_appointment(): void
    {
        // Der Rueckweg aus der Datenbank rekonstruiert die Zeitspanne aus zwei
        // Spalten und einem Kennzeichen. Geht das schief, wird aus einem
        // ganzen Tag ein Termin von 00:00 bis 00:00.
        $appointment = Appointment::schedule(
            title: 'Betriebsausflug',
            when: TimeSpan::allDay(new \DateTimeImmutable('2026-08-20 00:00:00', new \DateTimeZone('UTC')), 2),
        );

        $when = $appointment->when();

        self::assertTrue($when->allDay);
        self::assertSame('2026-08-22 00:00:00', $when->end->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function a_very_long_title_is_cut_instead_of_refused(): void
    {
        // Die Spalte ist 200 Zeichen breit. Ein Abbruch mit Fehlermeldung
        // waere hier unfreundlich - der Titel ist keine fachliche Regel,
        // sondern eine Speichergrenze.
        $appointment = Appointment::schedule(title: str_repeat('a', 500), when: $this->span());

        self::assertSame(200, mb_strlen($appointment->title()));
    }

    private function appointment(): Appointment
    {
        return Appointment::schedule(title: 'Vor-Ort-Termin', when: $this->span());
    }

    private function span(): TimeSpan
    {
        return TimeSpan::of(
            new \DateTimeImmutable('2026-08-20 10:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2026-08-20 11:30:00', new \DateTimeZone('UTC')),
        );
    }
}
