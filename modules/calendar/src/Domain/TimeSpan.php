<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

/**
 * Anfang und Ende eines Termins - immer in UTC.
 *
 * Die Umrechnung passiert hier und nicht irgendwo in der Oberflaeche. Ein
 * Termin, der mal in Berliner und mal in UTC-Zeit gespeichert wird, faellt
 * genau zweimal im Jahr auf: bei der Zeitumstellung, und dann ist es zu spaet.
 *
 * Angezeigt wird spaeter in der Zeitzone des Betrachters. Gespeichert und im
 * ICS-Feed ausgeliefert wird UTC - dafuer gibt es das "Z" am Ende, und jeder
 * Kalenderclient rechnet selbst um.
 */
final readonly class TimeSpan
{
    private function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $allDay,
    ) {
    }

    public static function of(\DateTimeImmutable $start, \DateTimeImmutable $end): self
    {
        $start = self::toUtc($start);
        $end = self::toUtc($end);

        if ($end < $start) {
            throw InvalidAppointment::endsBeforeItStarts();
        }

        return new self($start, $end, false);
    }

    /**
     * Ein ganztaegiger Termin.
     *
     * Ende ist der *Folgetag* um Mitternacht, nicht 23:59:59. So will es
     * RFC 5545, und so rechnen die Kalenderclients: DTEND ist exklusiv. Mit
     * 23:59:59 zeigt Outlook den Termin ueber zwei Tage an - eine Minute
     * Unterschied, ein Tag Wirkung.
     */
    public static function allDay(\DateTimeImmutable $day, int $days = 1): self
    {
        if ($days < 1) {
            throw InvalidAppointment::shorterThanADay();
        }

        // Hier wird bewusst *nicht* umgerechnet.
        //
        // Ein ganztaegiger Termin ist ein Datum, kein Zeitpunkt - im ICS steht
        // er als VALUE=DATE ganz ohne Zeitzone. Wer erst nach UTC umrechnet
        // und dann die Uhrzeit abschneidet, verliert oestlich von Greenwich
        // einen Tag: aus Mitternacht in Berlin wird 22:00 des Vortags, und
        // daraus 00:00 des Vortags.
        //
        // format() liest das Datum in der Zeitzone des Werts - also das
        // Datum, das der Benutzer gemeint hat.
        $start = new \DateTimeImmutable($day->format('Y-m-d').' 00:00:00', new \DateTimeZone('UTC'));

        return new self($start, $start->modify(sprintf('+%d days', $days)), true);
    }

    public function durationInMinutes(): int
    {
        return intdiv($this->end->getTimestamp() - $this->start->getTimestamp(), 60);
    }

    public function overlaps(self $other): bool
    {
        // Beruehrung ist keine Ueberschneidung: ein Termin von 10 bis 11 und
        // einer von 11 bis 12 gehen problemlos hintereinander.
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function containsInstant(\DateTimeImmutable $moment): bool
    {
        $moment = self::toUtc($moment);

        return $moment >= $this->start && $moment < $this->end;
    }

    private static function toUtc(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        // setTimezone rechnet den Zeitpunkt um, es beschriftet ihn nicht neu.
        // 12:00 Berlin wird zu 10:00 UTC - derselbe Augenblick, andere
        // Schreibweise.
        return $moment->setTimezone(new \DateTimeZone('UTC'));
    }
}
