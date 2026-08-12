<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

/**
 * Wie eine Kennzahl zu lesen ist.
 *
 * Bewusst nicht "rot, gelb, gruen": das waere eine Anweisung ans Template und
 * damit eine Designentscheidung im Vertrag. Der Ton sagt, *was* der Wert
 * bedeutet - wie das aussieht, entscheidet die Oberflaeche.
 */
enum MetricTone: string
{
    /**
     * Eine Zahl ohne Wertung - etwa "8 Kontakte".
     */
    case NEUTRAL = 'neutral';

    /**
     * Etwas laeuft gut und darf auffallen.
     */
    case POSITIVE = 'positive';

    /**
     * Etwas verlangt Aufmerksamkeit - ueberfaellige Aufgaben etwa.
     */
    case ATTENTION = 'attention';

    public function isNotable(): bool
    {
        return self::NEUTRAL !== $this;
    }
}
