<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Menu;

/**
 * Ein Eintrag in der globalen Navigation.
 *
 * Bewusst ein reiner Wert ohne Verhalten: Module erzeugen ihn, der Core
 * rendert ihn. Beide Seiten muessen sich nur auf diese vier Felder einigen.
 */
final readonly class MenuItem
{
    /**
     * @param string $label    Uebersetzungsschluessel, kein fertiger Text -
     *                         das Layout ruft ihn durch |trans.
     * @param string $route    Symfony-Routenname, nicht die URL.
     * @param string $icon     Icon-Bezeichner, vom Template interpretiert.
     * @param int    $priority Hoeher = weiter vorne. Gleichstand wird nach Label sortiert.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon = 'dot',
        public int $priority = 0,
    ) {
    }
}
