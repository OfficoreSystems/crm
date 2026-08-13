<?php

declare(strict_types=1);

namespace Crm\Calendar\Infrastructure\Ics;

use Crm\Calendar\Domain\Appointment;

/**
 * Schreibt einen Kalender nach RFC 5545.
 *
 * Von Hand und nicht mit einer Bibliothek: das Format ist ueberschaubar, und
 * die Stellen, an denen echte Clients aussteigen, sind genau die, die eine
 * Bibliothek verstecken wuerde. Sie stehen hier alle mit Testfall:
 *
 *   - Zeilenenden sind CRLF. Ein reines "\n" laesst Outlook den Feed
 *     kommentarlos als leer ansehen.
 *   - Zeilen werden bei 75 Oktetten umgebrochen, Fortsetzung mit einem
 *     Leerzeichen. Oktette, nicht Zeichen - ein Umbruch mitten in einem
 *     Umlaut ergibt Datenmuell.
 *   - In TEXT-Werten muessen \ ; , und Zeilenumbrueche maskiert werden. Ein
 *     Komma im Titel zerlegt sonst den Wert in zwei.
 *   - DTEND ist exklusiv. Bei ganztaegigen Terminen ist es der Folgetag.
 *   - UID muss weltweit eindeutig und ueber Aktualisierungen stabil sein,
 *     sonst legt der Client jedes Mal einen neuen Termin an.
 */
final readonly class IcsWriter
{
    private const CRLF = "\r\n";
    private const MAX_OCTETS = 75;

    public function __construct(
        /**
         * Taucht in der UID hinter dem @ auf. Kein Netzwerkzugriff, nur ein
         * Namensraum - so kollidieren unsere UIDs nicht mit denen anderer
         * Systeme im selben Kalender.
         */
        private string $domain = 'officore.crm',
    ) {
    }

    /**
     * @param list<Appointment> $appointments
     */
    public function calendar(array $appointments, string $name, ?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Officore//CRM//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            // Nicht im Standard, aber von Google, Outlook und Apple
            // ausgewertet - ohne das heisst das Abonnement nach der URL.
            $this->property('X-WR-CALNAME', $name),
            $this->property('X-WR-TIMEZONE', 'UTC'),
        ];

        foreach ($appointments as $appointment) {
            $lines = [...$lines, ...$this->event($appointment, $now)];
        }

        $lines[] = 'END:VCALENDAR';

        // Abschliessendes CRLF: RFC 5545 verlangt, dass jede Zeile damit
        // endet - auch die letzte.
        return implode(self::CRLF, array_map($this->fold(...), $lines)).self::CRLF;
    }

    /**
     * @return list<string>
     */
    private function event(Appointment $appointment, \DateTimeImmutable $now): array
    {
        $when = $appointment->when();

        $lines = [
            'BEGIN:VEVENT',
            $this->property('UID', $appointment->id()->toRfc4122().'@'.$this->domain),
            'DTSTAMP:'.$this->utc($now),
            'SEQUENCE:'.$appointment->sequence(),
            $this->property('SUMMARY', $appointment->title()),
        ];

        if ($appointment->isAllDay()) {
            // Wertetyp DATE statt DATE-TIME: sonst zeigen Clients einen
            // Termin von 00:00 bis 00:00 statt eines ganzen Tages.
            $lines[] = 'DTSTART;VALUE=DATE:'.$when->start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$when->end->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.$this->utc($when->start);
            $lines[] = 'DTEND:'.$this->utc($when->end);
        }

        if (null !== $appointment->description()) {
            $lines[] = $this->property('DESCRIPTION', $appointment->description());
        }

        if (null !== $appointment->location()) {
            $lines[] = $this->property('LOCATION', $appointment->location());
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function property(string $name, string $value): string
    {
        return $name.':'.$this->escape($value);
    }

    /**
     * Der Zeitstempel im UTC-Format mit "Z".
     *
     * Das Z ist die ganze Zeitzonenbehandlung dieses Feeds: es sagt dem
     * Client "das ist UTC", und der rechnet in die Zeitzone seines Benutzers
     * um. Ohne Z gilt die Angabe als Ortszeit - und dieselbe Datei zeigt in
     * Berlin und in Tokio verschiedene Termine.
     */
    private function utc(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Maskiert einen TEXT-Wert.
     *
     * Reihenfolge ist wichtig: der Backslash zuerst, sonst maskiert man die
     * gerade eingefuegten Backslashes gleich noch einmal.
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    /**
     * Bricht eine zu lange Zeile nach RFC 5545 um.
     *
     * Gezaehlt wird in Oktetten, nicht in Zeichen. Ein Umbruch mitten durch
     * ein mehrbyte-Zeichen macht die Datei kaputt, deshalb wird
     * zeichenweise gefuellt, bis das naechste Zeichen nicht mehr passt.
     */
    private function fold(string $line): string
    {
        if (\strlen($line) <= self::MAX_OCTETS) {
            return $line;
        }

        $folded = '';
        $current = '';
        // Die Fortsetzungszeile beginnt mit einem Leerzeichen, das selbst ein
        // Oktett belegt.
        $limit = self::MAX_OCTETS;

        foreach (preg_split('//u', $line, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (\strlen($current) + \strlen($char) > $limit) {
                $folded .= $current.self::CRLF.' ';
                $current = '';
                $limit = self::MAX_OCTETS - 1;
            }

            $current .= $char;
        }

        return $folded.$current;
    }
}
