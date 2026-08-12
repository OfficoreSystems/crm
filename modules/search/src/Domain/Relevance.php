<?php

declare(strict_types=1);

namespace Crm\Search\Domain;

use Crm\SharedKernel\Subject\ResolvedSubject;

/**
 * Bewertet, wie gut ein Treffer zur Eingabe passt.
 *
 * Bewusst eine simple Heuristik statt Volltextindex: die einzelnen Module
 * suchen bereits in ihren eigenen Tabellen: was hier ankommt, ist schon
 * gefiltert. Zu entscheiden bleibt nur die Reihenfolge *zwischen* den Modulen,
 * und dafuer reicht "genau getroffen schlaegt Anfang schlaegt irgendwo".
 *
 * Ein echter Index (Meilisearch, Elasticsearch) wuerde sich erst lohnen, wenn
 * Volltext in Notizen und Dokumenten dazukommt. Bis dahin waere er
 * Infrastruktur, die niemand betreiben will.
 */
final class Relevance
{
    public const EXACT = 100;
    public const PREFIX = 75;
    public const CONTAINS = 50;
    public const DESCRIPTION = 25;
    public const WEAK = 10;

    public static function score(ResolvedSubject $subject, string $query): int
    {
        $needle = self::normalize($query);

        if ('' === $needle) {
            return self::WEAK;
        }

        $label = self::normalize($subject->label);

        if ($label === $needle) {
            return self::EXACT;
        }

        if (str_starts_with($label, $needle)) {
            return self::PREFIX;
        }

        if (str_contains($label, $needle)) {
            return self::CONTAINS;
        }

        if (null !== $subject->description && str_contains(self::normalize($subject->description), $needle)) {
            return self::DESCRIPTION;
        }

        // Das Modul hat den Datensatz geliefert, also passt er irgendwie -
        // vielleicht ueber ein Feld, das gar nicht angezeigt wird. Er faellt
        // ans Ende, verschwindet aber nicht.
        return self::WEAK;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
