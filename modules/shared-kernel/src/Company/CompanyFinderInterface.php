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
     * Sagt, ob eine ID auf eine existierende Firma zeigt.
     *
     * Gedacht fuer Module, die eine Firmen-ID skalar speichern und beim
     * Setzen pruefen wollen - eine Fremdschluesselbeziehung gibt es ueber
     * Modulgrenzen hinweg nicht.
     */
    public function exists(string $id): bool;
}
