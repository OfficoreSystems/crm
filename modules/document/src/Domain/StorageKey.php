<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Der Schluessel, unter dem eine Datei im Objektspeicher liegt.
 *
 * Bewusst ohne jeden Anteil aus der Benutzereingabe. Ein Schluessel aus dem
 * Dateinamen haette zwei Probleme, von denen das zweite das schlimmere ist:
 * zwei Benutzer wuerden sich gegenseitig ueberschreiben, und wer den
 * Dateinamen kennt, kennt den Speicherort.
 *
 * Aufbau: `<subjekt-typ>/<jahr>/<monat>/<uuid>`. Die Datumsebene ist keine
 * Ordnung fuer Menschen - sie haelt die Praefixe klein genug, dass ein
 * spaeteres Auflisten oder Aufraeumen nicht ueber Millionen Objekte laeuft.
 */
final class StorageKey
{
    public static function for(string $subjectType, ?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable();
        $type = preg_replace('/[^a-z0-9-]/', '', strtolower($subjectType)) ?? '';

        return sprintf(
            '%s/%s/%s',
            '' === $type ? 'sonstiges' : $type,
            $now->format('Y/m'),
            Uuid::v7()->toRfc4122(),
        );
    }
}
