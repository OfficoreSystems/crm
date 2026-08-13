<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

/**
 * Macht aus einem Dateinamen aus einer Anfrage einen, den man anzeigen und
 * ausliefern kann.
 *
 * Ein hochgeladener Dateiname ist Benutzereingabe wie jede andere - nur
 * gefaehrlicher, weil er an drei Stellen landet: in der Datenbank, in einem
 * Content-Disposition-Header und im Browser des naechsten Betrachters. Deshalb
 * fliegen Pfadanteile, Steuerzeichen und Zeilenumbrueche raus, bevor der Name
 * die Domain erreicht.
 *
 * Der Name wird ausdruecklich *nicht* zum Speicherschluessel - siehe
 * {@see StorageKey}. Zwei Benutzer duerfen "Angebot.pdf" hochladen, ohne sich
 * gegenseitig zu ueberschreiben.
 */
final class SafeFilename
{
    public const MAX_LENGTH = 200;

    public static function from(string $raw): string
    {
        // basename() allein reicht nicht: es kennt unter Linux nur "/", ein
        // Windows-Client schickt aber "C:\Users\...\Angebot.pdf".
        $name = str_replace('\\', '/', $raw);
        $name = basename($name);

        // Steuerzeichen und Zeilenumbrueche. Ein "\r\n" im Namen waere eine
        // Header-Injection in Content-Disposition.
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        // Fuehrende Punkte: ".htaccess" oder "..\" haben in einer Anzeige
        // nichts verloren und in einem Pfad erst recht nicht.
        $name = ltrim($name, '. ');
        $name = trim($name);

        if ('' === $name) {
            return 'datei';
        }

        return self::shorten($name);
    }

    /**
     * Kuerzt in der Mitte statt am Ende - die Endung entscheidet, womit der
     * Browser die Datei oeffnet, und waere am Ende als Erstes weg.
     */
    private static function shorten(string $name): string
    {
        if (mb_strlen($name) <= self::MAX_LENGTH) {
            return $name;
        }

        $extension = pathinfo($name, \PATHINFO_EXTENSION);
        $extension = '' === $extension ? '' : '.'.mb_substr($extension, 0, 20);

        $stem = mb_substr($name, 0, self::MAX_LENGTH - mb_strlen($extension));

        return $stem.$extension;
    }
}
