<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

/**
 * Extension-Point: ein Modul macht seine Datensaetze als Subjekt verweisbar.
 *
 * Damit koennen andere Module - activity heute, document und email spaeter -
 * etwas an einen Kontakt, eine Firma oder eine Verkaufschance haengen, ohne
 * eines dieser Module zu kennen.
 *
 * Implementierungen werden ueber registerForAutoconfiguration() automatisch
 * mit `crm.subject_resolver` getaggt.
 *
 * Zur Signatur von resolve(): sie nimmt eine *Liste* von IDs, nicht eine
 * einzelne. Eine Timeline zeigt dutzende Eintraege, und ein Aufruf je Eintrag
 * waere ein N+1 ueber eine Modulgrenze. Die Registry gruppiert deshalb nach
 * Typ und ruft jeden Resolver genau einmal auf.
 */
interface SubjectResolverInterface
{
    /**
     * Der Typ, den dieses Modul aufloest - z. B. "contact".
     */
    public function type(): string;

    /**
     * Anzeigename des Typs, z. B. "Kontakt".
     */
    public function typeLabel(): string;

    /**
     * @param list<string> $ids
     *
     * @return array<string, ResolvedSubject> Indiziert nach ID. Unbekannte
     *                                        IDs fehlen im Ergebnis.
     */
    public function resolve(array $ids): array;

    /**
     * Kandidaten fuer eine Auswahl.
     *
     * Aufloesen allein reicht nicht: wer etwas an ein Subjekt haengen will -
     * eine Aktivitaet, spaeter ein Dokument oder eine E-Mail -, muss vorher
     * eines auswaehlen koennen. Ohne diese Methode muesste jedes solche Modul
     * die konkreten Finder von contact, company und deal kennen, und der
     * Extension-Point waere nur halb.
     *
     * Ein leerer Suchbegriff liefert die ersten Eintraege, nicht keine - das
     * ist das erwartete Verhalten eines Auswahlfelds beim Oeffnen.
     *
     * @return list<ResolvedSubject>
     */
    public function search(string $query, int $limit = 10): array;
}
