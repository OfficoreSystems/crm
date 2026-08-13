<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Domain;

use Crm\Calendar\Domain\TimeSpan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Zeitzonenregel des Moduls, an einer Stelle festgehalten.
 *
 * Ein Termin, der mal in Berliner und mal in UTC-Zeit gespeichert wird, faellt
 * genau zweimal im Jahr auf - und dann ist es zu spaet.
 */
final class TimeSpanTest extends TestCase
{
    #[Test]
    public function a_local_time_becomes_the_same_instant_in_utc(): void
    {
        // setTimezone rechnet um, es beschriftet nicht neu: 14:00 Berlin und
        // 12:00 UTC sind derselbe Augenblick.
        $berlin = new \DateTimeImmutable('2026-08-20 14:00:00', new \DateTimeZone('Europe/Berlin'));

        $span = TimeSpan::of($berlin, $berlin->modify('+90 minutes'));

        self::assertSame('UTC', $span->start->getTimezone()->getName());
        self::assertSame('2026-08-20 12:00:00', $span->start->format('Y-m-d H:i:s'));
        self::assertSame($berlin->getTimestamp(), $span->start->getTimestamp());
    }

    #[Test]
    public function winter_and_summer_shift_by_different_amounts(): void
    {
        // Der Fall, den eine feste Verschiebung um zwei Stunden verschluckt.
        $summer = new \DateTimeImmutable('2026-08-20 14:00:00', new \DateTimeZone('Europe/Berlin'));
        $winter = new \DateTimeImmutable('2026-01-20 14:00:00', new \DateTimeZone('Europe/Berlin'));

        self::assertSame('12:00', TimeSpan::of($summer, $summer)->start->format('H:i'), 'Sommerzeit: UTC+2');
        self::assertSame('13:00', TimeSpan::of($winter, $winter)->start->format('H:i'), 'Winterzeit: UTC+1');
    }

    #[Test]
    public function an_appointment_across_the_time_change_keeps_its_wall_clock_duration(): void
    {
        // In der Nacht der Umstellung ist eine Stunde Ortszeit nicht eine
        // Stunde absolute Zeit. Wer in Ortszeit rechnet, bekommt hier eine
        // Stunde geschenkt oder verliert eine.
        $before = new \DateTimeImmutable('2026-10-25 01:30:00', new \DateTimeZone('Europe/Berlin'));
        $after = new \DateTimeImmutable('2026-10-25 03:30:00', new \DateTimeZone('Europe/Berlin'));

        $span = TimeSpan::of($before, $after);

        // 01:30 MESZ bis 03:30 MEZ sind drei echte Stunden, nicht zwei.
        self::assertSame(180, $span->durationInMinutes());
    }

    #[Test]
    public function an_end_before_the_start_is_refused(): void
    {
        $this->expectExceptionMessage('enden, bevor er beginnt');

        TimeSpan::of(
            new \DateTimeImmutable('2026-08-20 15:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2026-08-20 14:00:00', new \DateTimeZone('UTC')),
        );
    }

    #[Test]
    public function a_zero_length_appointment_is_allowed(): void
    {
        // Ein Zeitpunkt ohne Dauer ist ungewoehnlich, aber nicht falsch -
        // etwa eine Erinnerung.
        $moment = new \DateTimeImmutable('2026-08-20 14:00:00', new \DateTimeZone('UTC'));

        self::assertSame(0, TimeSpan::of($moment, $moment)->durationInMinutes());
    }

    #[Test]
    public function an_all_day_appointment_ends_on_the_next_day(): void
    {
        // Und nicht um 23:59:59. So will es RFC 5545, und so rechnen die
        // Clients: DTEND ist exklusiv.
        $span = TimeSpan::allDay(new \DateTimeImmutable('2026-08-20 17:23:00', new \DateTimeZone('UTC')));

        self::assertTrue($span->allDay);
        self::assertSame('2026-08-20 00:00:00', $span->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-21 00:00:00', $span->end->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function an_all_day_appointment_keeps_the_date_the_user_meant(): void
    {
        // Der Fehler, den ein Funktionstest gefunden hat: erst nach UTC
        // umrechnen und dann die Uhrzeit abschneiden verliert oestlich von
        // Greenwich einen Tag. Aus Mitternacht in Berlin wird 22:00 des
        // Vortags - und daraus 00:00 des Vortags.
        $berlinMidnight = new \DateTimeImmutable('2026-08-20 00:00:00', new \DateTimeZone('Europe/Berlin'));

        $span = TimeSpan::allDay($berlinMidnight);

        self::assertSame('2026-08-20', $span->start->format('Y-m-d'));
        self::assertSame('2026-08-21', $span->end->format('Y-m-d'));
    }

    #[Test]
    public function the_same_holds_west_of_greenwich(): void
    {
        // Dort liegt der Fehler andersherum: aus Mitternacht in New York
        // wuerde 05:00 desselben Tages, und das Abschneiden waere zufaellig
        // richtig. Der Test steht hier, damit eine Ruecknahme der Korrektur
        // nicht nur in einer Richtung auffaellt.
        $newYorkMidnight = new \DateTimeImmutable('2026-08-20 00:00:00', new \DateTimeZone('America/New_York'));

        self::assertSame('2026-08-20', TimeSpan::allDay($newYorkMidnight)->start->format('Y-m-d'));
    }

    #[Test]
    public function an_all_day_appointment_can_span_several_days(): void
    {
        $span = TimeSpan::allDay(new \DateTimeImmutable('2026-08-20 00:00:00', new \DateTimeZone('UTC')), 3);

        self::assertSame('2026-08-23 00:00:00', $span->end->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function zero_days_are_refused(): void
    {
        $this->expectExceptionMessage('mindestens einen Tag');

        TimeSpan::allDay(new \DateTimeImmutable('2026-08-20 00:00:00', new \DateTimeZone('UTC')), 0);
    }

    #[Test]
    public function touching_appointments_do_not_overlap(): void
    {
        // Ein Termin von 10 bis 11 und einer von 11 bis 12 gehen problemlos
        // hintereinander. Mit >= statt > waere jeder Terminblock ein Konflikt.
        $first = $this->span('10:00', '11:00');
        $second = $this->span('11:00', '12:00');

        self::assertFalse($first->overlaps($second));
        self::assertFalse($second->overlaps($first));
    }

    #[Test]
    public function real_overlaps_are_recognised_from_both_sides(): void
    {
        $first = $this->span('10:00', '12:00');
        $second = $this->span('11:00', '13:00');

        self::assertTrue($first->overlaps($second));
        self::assertTrue($second->overlaps($first));
    }

    #[Test]
    public function the_end_is_not_part_of_the_appointment(): void
    {
        $span = $this->span('10:00', '11:00');

        self::assertTrue($span->containsInstant($this->moment('10:00')));
        self::assertTrue($span->containsInstant($this->moment('10:59')));
        self::assertFalse($span->containsInstant($this->moment('11:00')), 'Sonst gehoert 11:00 zu zwei Terminen.');
    }

    private function span(string $from, string $to): TimeSpan
    {
        return TimeSpan::of($this->moment($from), $this->moment($to));
    }

    private function moment(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-20 '.$time.':00', new \DateTimeZone('UTC'));
    }
}
