<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Company;

/**
 * Extension point: look up companies without knowing the company module.
 *
 * Read access only. The default implementation is {@see NullCompanyFinder}; once
 * the company module is installed it overrides the alias.
 */
interface CompanyFinderInterface
{
    public function find(string $id): ?CompanySummary;

    /**
     * @param list<string> $ids
     *
     * @return array<string, CompanySummary> Indexed by ID. Unknown IDs are
     *                                       missing from the result.
     */
    public function findMany(array $ids): array;

    /**
     * For select fields in other modules.
     *
     * @return list<CompanySummary>
     */
    public function findAll(): array;

    /**
     * Search companies by name.
     *
     * The reason this lives here: a module that stores company IDs as scalars
     * cannot answer "show me everything for the company Nordwind" by itself - a
     * join across the module boundary is out of the question. Instead it
     * resolves the name to IDs here and filters its own table by them. Two
     * queries instead of one join, and the boundary stays intact.
     *
     * @return list<CompanySummary>
     */
    public function searchByName(string $query, int $limit = 25): array;

    /**
     * States whether an ID points at an existing company.
     *
     * Meant for modules that store a company ID as a scalar and want to check on
     * assignment - a foreign key relation does not exist across module
     * boundaries.
     */
    public function exists(string $id): bool;
}
