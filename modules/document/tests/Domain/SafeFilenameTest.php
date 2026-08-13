<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Domain;

use Crm\Document\Domain\SafeFilename;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Der Dateiname ist die gefaehrlichste Eingabe dieses Moduls.
 *
 * Er kommt aus dem Browser, landet in der Datenbank, in einem
 * Content-Disposition-Header und im Browser des naechsten Betrachters. Diese
 * Tabelle ist deshalb ausfuehrlicher als der Code, den sie prueft.
 */
final class SafeFilenameTest extends TestCase
{
    #[Test]
    #[DataProvider('names')]
    public function it_strips_what_does_not_belong_in_a_filename(string $raw, string $expected, string $why): void
    {
        self::assertSame($expected, SafeFilename::from($raw), $why);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function names(): iterable
    {
        yield 'harmlos' => ['Angebot.pdf', 'Angebot.pdf', 'Ein normaler Name bleibt unveraendert.'];

        yield 'Umlaute bleiben' => [
            'Jahresabschluss Prüfung.pdf',
            'Jahresabschluss Prüfung.pdf',
            'Kein Transliterieren - der Name wird angezeigt, nicht als Pfad benutzt.',
        ];

        yield 'Unix-Pfad' => [
            '/etc/passwd',
            'passwd',
            'Nur der letzte Teil bleibt uebrig.',
        ];

        yield 'Windows-Pfad' => [
            'C:\\Users\\Jeremy\\Angebot.pdf',
            'Angebot.pdf',
            'basename() allein kennt unter Linux kein "\\" - deshalb die Ersetzung davor.',
        ];

        yield 'Verzeichniswechsel' => [
            '../../etc/passwd',
            'passwd',
            'Der Klassiker.',
        ];

        yield 'Verzeichniswechsel mit Backslash' => [
            '..\\..\\windows\\system32\\config',
            'config',
            'Dieselbe Absicht, andere Schreibweise.',
        ];

        yield 'Zeilenumbruch' => [
            "Angebot.pdf\r\nX-Injected: 1",
            'Angebot.pdfX-Injected: 1',
            'Ein "\r\n" im Namen waere eine Header-Injection in Content-Disposition.',
        ];

        yield 'Nullbyte' => [
            "Angebot.pdf\0.exe",
            'Angebot.pdf.exe',
            'Nullbytes schneiden Zeichenketten in aelteren Funktionen ab.',
        ];

        yield 'nur Punkte' => [
            '..',
            'datei',
            'Waere sonst leer - und ein leerer Name ist im Header ungueltig.',
        ];

        yield 'versteckte Datei' => [
            '.htaccess',
            'htaccess',
            'Fuehrende Punkte haben in einer Anzeige nichts verloren.',
        ];

        yield 'leer' => ['', 'datei', 'Es muss immer etwas uebrig bleiben.'];

        yield 'nur Leerzeichen' => ['     ', 'datei', 'Wie leer.'];
    }

    #[Test]
    public function a_very_long_name_keeps_its_extension(): void
    {
        // Am Ende zu kuerzen waere naheliegend und falsch: die Endung
        // entscheidet, womit der Browser die Datei oeffnet, und waere als
        // Erstes weg.
        $name = str_repeat('a', 400).'.pdf';

        $safe = SafeFilename::from($name);

        self::assertSame(SafeFilename::MAX_LENGTH, mb_strlen($safe));
        self::assertStringEndsWith('.pdf', $safe);
    }

    #[Test]
    public function a_long_name_without_an_extension_is_simply_cut(): void
    {
        $safe = SafeFilename::from(str_repeat('b', 400));

        self::assertSame(SafeFilename::MAX_LENGTH, mb_strlen($safe));
    }
}
