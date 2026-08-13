<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Company;

/**
 * Default implementation for as long as no company module is installed.
 *
 * Returns empty answers rather than throwing. Callers have to expect an
 * unresolvable company anyway - even with the company module a stored ID can be
 * out of date.
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

    public function searchByName(string $query, int $limit = 25): array
    {
        return [];
    }

    public function exists(string $id): bool
    {
        return false;
    }
}
