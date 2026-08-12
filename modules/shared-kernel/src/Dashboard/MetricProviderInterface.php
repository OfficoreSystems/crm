<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

/**
 * Extension-Point: ein Modul liefert Kennzahlen fuer die Startseite.
 *
 * Wie beim Menue meldet sich ein Modul allein durch die Implementierung an -
 * das Dashboard hat keine Liste, in die man sich eintragen muesste.
 *
 * Die Kennzahlen kommen **fertig aggregiert**. Das Dashboard rechnet nichts
 * und fragt keine fremden Tabellen ab; jedes Modul zaehlt selbst, in seiner
 * eigenen Datenbank, mit den Abfragen die es ohnehin hat.
 */
interface MetricProviderInterface
{
    /**
     * @return iterable<Metric>
     */
    public function getMetrics(): iterable;
}
