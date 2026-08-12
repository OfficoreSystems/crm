<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Company;

/**
 * Standardimplementierung, solange kein company-Modul installiert ist.
 *
 * Liefert leere Antworten statt zu werfen. Aufrufer muessen ohnehin damit
 * rechnen, dass eine Firma nicht aufloesbar ist - auch mit company-Modul kann
 * eine gespeicherte ID veraltet sein.
 */
final class NullCompanyFinder implements CompanyFinderInterface
{
    public function find(string $id): ?CompanySummary
    {
        return null;
    }

    public function findMany(array $ids): array
    {
        return [];
    }

    public function findAll(): array
    {
        return [];
    }

    public function exists(string $id): bool
    {
        return false;
    }
}
