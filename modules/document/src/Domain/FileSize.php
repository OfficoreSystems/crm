<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

/**
 * Bytes in etwas, das ein Mensch lesen kann.
 *
 * Steht in der Domain, weil drei Schichten sie brauchen: die Kennzahl auf der
 * Uebersicht, die Fehlermeldung beim zu grossen Upload und die Tabelle im
 * Template. Drei Kopien wuerden auseinanderlaufen, und "25.0 MB" gegen
 * "25 MB" in derselben Anwendung sieht nach Zufall aus.
 */
final class FileSize
{
    public static function humanize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < \count($units) - 1) {
            $value /= 1024;
            ++$unit;
        }

        // Bytes ohne Nachkommastelle: "512.0 B" liest sich falsch.
        return 0 === $unit
            ? sprintf('%d %s', (int) $value, $units[$unit])
            : sprintf('%.1f %s', $value, $units[$unit]);
    }
}
