<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Company;

/**
 * Extension-Point: Firmen nachschlagen, ohne das company-Modul zu kennen.
 *
 * Nur Lesezugriff. Die Standardimplementierung ist {@see NullCompanyFinder};
 * ist das company-Modul installiert, ueberschreibt es den Alias.
 */
interface CompanyFinderInterface
{
    public function find(string $id): ?CompanySummary;

    /**
     * @param list<string> $ids
     *
     * @return array<string, CompanySummary> Indiziert nach ID. Unbekannte IDs
     *                                       fehlen im Ergebnis.
     */
    public function findMany(array $ids): array;

    /**
     * Fuer Auswahlfelder in anderen Modulen.
     *
     * @return list<CompanySummary>
     */
    public function findAll(): array;

    /**
     * Firmen nach Namen suchen.
     *
     * Der Grund, warum das hier steht: ein Modul, das Firmen-IDs skalar
     * speichert, kann "zeig mir alles zur Firma Nordwind" nicht selbst
     * beantworten - ein Join ueber die Modulgrenze ist ausgeschlossen.
     * Stattdessen loest es den Namen hier zu IDs auf und filtert damit seine
     * eigene Tabelle. Zwei Abfragen statt eines Joins, dafuer bleibt die
     * Grenze intakt.
     *
     * @return list<CompanySummary>
     */
    public function searchByName(string $query, int $limit = 25): array;

    /**
     * Sagt, ob eine ID auf eine existierende Firma zeigt.
     *
     * Gedacht fuer Module, die eine Firmen-ID skalar speichern und beim
     * Setzen pruefen wollen - eine Fremdschluesselbeziehung gibt es ueber
     * Modulgrenzen hinweg nicht.
     */
    public function exists(string $id): bool;
}
