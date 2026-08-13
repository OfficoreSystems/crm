<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Extension-Point: ein Modul sagt, wem seine Datensaetze gehoeren.
 *
 * Damit kann der Voter im Shared Kernel ueber Zugriffe entscheiden, ohne ein
 * einziges Modul zu kennen. Er fragt: "welches Modul, wer ist Besitzer,
 * welches Team" - und vergleicht das mit der Rechtematrix.
 *
 * Implementierungen werden ueber registerForAutoconfiguration() automatisch
 * mit `crm.record_ownership` getaggt.
 *
 * Ein Modul, dessen Daten allen gehoeren - Stammdaten wie Firmen etwa -
 * braucht keine Implementierung. Solche Datensaetze sind dann nur mit
 * ALL-Rechten erreichbar, und genau das ist fuer gemeinsames Wissen richtig.
 */
interface RecordOwnershipInterface
{
    /**
     * Das Modul, zu dem die Datensaetze gehoeren - z. B. "deal".
     *
     * Der Schluessel, unter dem die Rechtematrix nachschlaegt.
     */
    public function module(): string;

    public function supports(object $record): bool;

    /**
     * Nur aufgerufen, wenn supports() zugestimmt hat.
     */
    public function ownershipOf(object $record): RecordOwnership;

    /**
     * Die Spalten, an denen der Sichtbarkeitsfilter einschraenken kann - oder
     * null, wenn dieses Modul keine eigene Tabelle dafuer hat.
     *
     * Null heisst nicht "unbeschraenkt": der Voter prueft weiterhin jeden
     * einzelnen Datensatz. Es heisst nur, dass die Einschraenkung nicht schon
     * in SQL passieren kann.
     */
    public function restrictedColumns(): ?RestrictedColumns;
}
