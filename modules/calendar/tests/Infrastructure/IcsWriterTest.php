<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Infrastructure;

use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\TimeSpan;
use Crm\Calendar\Infrastructure\Ics\IcsWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Datei, die in fremden Kalendern landet.
 *
 * Fehler hier sind besonders unangenehm, weil sie nicht in unserer Anwendung
 * auffallen: der Feed sieht im Browser vernuenftig aus, und Outlook zeigt
 * trotzdem nichts an. Deshalb prueft diese Datei das Format bis auf die
 * Zeilenenden.
 */
final class IcsWriterTest extends TestCase
{
    private const NOW = '2026-08-13 09:00:00';

    #[Test]
    public function every_line_ends_with_crlf(): void
    {
        // Ein reines "\n" laesst Outlook den Feed kommentarlos als leer
        // ansehen - der haeufigste Fehler bei selbstgebauten ICS-Dateien.
        $ics = $this->write([$this->appointment()]);

        self::assertStringEndsWith("\r\n", $ics);
        self::assertSame(0, preg_match('/(?<!\r)\n/', $ics), 'Es darf kein einzelnes \n ohne \r geben.');
    }

    #[Test]
    public function it_carries_the_required_envelope(): void
    {
        $ics = $this->write([$this->appointment()]);

        foreach (['BEGIN:VCALENDAR', 'VERSION:2.0', 'CALSCALE:GREGORIAN', 'END:VCALENDAR'] as $required) {
            self::assertStringContainsString($required."\r\n", $ics);
        }
    }

    #[Test]
    public function times_are_written_as_utc_with_a_trailing_z(): void
    {
        // Das Z ist die ganze Zeitzonenbehandlung: ohne es gilt die Angabe
        // als Ortszeit, und dieselbe Datei zeigt in Berlin und Tokio
        // verschiedene Termine.
        $berlin = new \DateTimeImmutable('2026-08-13 14:00:00', new \DateTimeZone('Europe/Berlin'));

        $ics = $this->write([
            $this->appointment(when: TimeSpan::of($berlin, $berlin->modify('+1 hour'))),
        ]);

        // 14:00 Berlin im Sommer ist 12:00 UTC.
        self::assertStringContainsString('DTSTART:20260813T120000Z', $ics);
        self::assertStringContainsString('DTEND:20260813T130000Z', $ics);
    }

    #[Test]
    public function an_all_day_event_uses_dates_and_an_exclusive_end(): void
    {
        // Mit 23:59:59 statt des Folgetags zeigt Outlook den Termin ueber
        // zwei Tage an. Eine Minute Unterschied, ein Tag Wirkung.
        $ics = $this->write([
            $this->appointment(when: TimeSpan::allDay(new \DateTimeImmutable('2026-08-13 00:00:00'))),
        ]);

        self::assertStringContainsString('DTSTART;VALUE=DATE:20260813', $ics);
        self::assertStringContainsString('DTEND;VALUE=DATE:20260814', $ics);
        self::assertStringNotContainsString('T000000Z', $ics);
    }

    #[Test]
    public function a_comma_in_the_title_does_not_split_the_value(): void
    {
        // Unmaskiert macht das Komma aus einem Titel zwei Werte - und der
        // Termin heisst dann nur noch bis zum Komma.
        $ics = $this->write([$this->appointment(title: 'Termin mit Meier, Schulz & Co.')]);

        self::assertStringContainsString('SUMMARY:Termin mit Meier\\, Schulz & Co.', $ics);
    }

    #[Test]
    public function semicolons_and_backslashes_are_escaped_in_the_right_order(): void
    {
        // Erst der Backslash, dann der Rest. Andersherum maskiert man die
        // gerade eingefuegten Backslashes gleich noch einmal.
        $ics = $this->write([$this->appointment(title: 'Pfad C:\\Temp; danach')]);

        self::assertStringContainsString('SUMMARY:Pfad C:\\\\Temp\\; danach', $ics);
    }

    #[Test]
    public function line_breaks_in_the_description_become_the_escaped_form(): void
    {
        $ics = $this->write([
            $this->appointment(description: "Erste Zeile\r\nZweite Zeile\nDritte"),
        ]);

        self::assertStringContainsString('DESCRIPTION:Erste Zeile\\nZweite Zeile\\nDritte', $ics);
    }

    #[Test]
    public function long_lines_are_folded_at_seventy_five_octets(): void
    {
        $ics = $this->write([$this->appointment(title: str_repeat('a', 300))]);

        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line), 'Zeile zu lang: '.substr($line, 0, 40).'…');
        }
    }

    #[Test]
    public function folding_never_cuts_a_character_in_half(): void
    {
        // Gezaehlt wird in Oktetten. Ein Umbruch mitten durch ein Umlaut-Byte
        // ergibt Datenmuell, den der Client als kaputte Datei ansieht.
        $ics = $this->write([$this->appointment(title: str_repeat('ü', 200))]);

        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line));
        }

        // Nach dem Entfalten muss der Titel wieder vollstaendig da sein.
        $unfolded = str_replace("\r\n ", '', $ics);

        self::assertStringContainsString('SUMMARY:'.str_repeat('ü', 200), $unfolded);
        self::assertTrue(mb_check_encoding($ics, 'UTF-8'), 'Die Datei muss gueltiges UTF-8 bleiben.');
    }

    #[Test]
    public function the_uid_is_stable_and_globally_unique(): void
    {
        // Ohne stabile UID legt der Client bei jedem Abruf einen neuen Termin
        // an - der Kalender fuellt sich mit Dubletten.
        $appointment = $this->appointment();

        $first = $this->write([$appointment]);
        $second = $this->write([$appointment]);

        $uid = $appointment->id()->toRfc4122().'@officore.crm';

        self::assertStringContainsString('UID:'.$uid, $first);
        self::assertStringContainsString('UID:'.$uid, $second);
    }

    #[Test]
    public function a_changed_appointment_raises_its_sequence(): void
    {
        // Ohne steigende SEQUENCE ignorieren viele Clients die Aktualisierung
        // und zeigen weiter den alten Termin.
        $appointment = $this->appointment();
        self::assertStringContainsString('SEQUENCE:0', $this->write([$appointment]));

        $appointment->reschedule(TimeSpan::of(
            new \DateTimeImmutable('2026-09-01 10:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2026-09-01 11:00:00', new \DateTimeZone('UTC')),
        ));

        self::assertStringContainsString('SEQUENCE:1', $this->write([$appointment]));
    }

    #[Test]
    public function an_empty_calendar_is_still_a_valid_calendar(): void
    {
        // Ein neuer Benutzer hat keine Termine. Der Client darf daran nicht
        // scheitern - sonst sieht ein leerer Kalender aus wie ein kaputter.
        $ics = $this->write([]);

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        self::assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }

    #[Test]
    public function the_calendar_carries_a_readable_name(): void
    {
        // Ohne X-WR-CALNAME heisst das Abonnement in Google Calendar nach der
        // URL - eine Zeile Zufallszeichen in der Kalenderliste.
        self::assertStringContainsString('X-WR-CALNAME:Officore – Vera', $this->write([], 'Officore – Vera'));
    }

    #[Test]
    public function optional_fields_are_left_out_instead_of_written_empty(): void
    {
        // "LOCATION:" ohne Wert ist zwar zulaessig, aber manche Clients
        // zeigen dann eine leere Zeile im Termin an.
        $ics = $this->write([$this->appointment(description: null, location: null)]);

        self::assertStringNotContainsString('DESCRIPTION:', $ics);
        self::assertStringNotContainsString('LOCATION:', $ics);
    }

    /**
     * @param list<Appointment> $appointments
     */
    private function write(array $appointments, string $name = 'Officore'): string
    {
        return (new IcsWriter())->calendar(
            $appointments,
            $name,
            new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')),
        );
    }

    private function appointment(
        string $title = 'Vor-Ort-Termin',
        ?TimeSpan $when = null,
        ?string $description = 'Vorstellung des Leistungsumfangs',
        ?string $location = 'Hamburg',
    ): Appointment {
        return Appointment::schedule(
            title: $title,
            when: $when ?? TimeSpan::of(
                new \DateTimeImmutable('2026-08-20 10:00:00', new \DateTimeZone('UTC')),
                new \DateTimeImmutable('2026-08-20 11:30:00', new \DateTimeZone('UTC')),
            ),
            description: $description,
            location: $location,
        );
    }
}
