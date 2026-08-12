<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Double;

use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;

/**
 * Steht im Test fuer das company-Modul.
 *
 * Ohne Eintraege verhaelt es sich wie der NullCompanyFinder - also wie eine
 * Installation ganz ohne company-Modul. Genau dieser Fall muss in der
 * Kontaktliste funktionieren.
 */
class FakeCompanyFinder implements CompanyFinderInterface
{
    /**
     * @var array<string, CompanySummary>
     */
    private array $companies = [];

    public function add(CompanySummary $company): self
    {
        $this->companies[$company->id] = $company;

        return $this;
    }

    public function find(string $id): ?CompanySummary
    {
        return $this->companies[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            if (isset($this->companies[$id])) {
                $found[$id] = $this->companies[$id];
            }
        }

        return $found;
    }

    public function findAll(): array
    {
        return array_values($this->companies);
    }

    public function searchByName(string $query, int $limit = 25): array
    {
        $needle = mb_strtolower(trim($query));

        if ('' === $needle) {
            return [];
        }

        return \array_slice(array_values(array_filter(
            $this->companies,
            static fn (CompanySummary $c): bool => str_contains(mb_strtolower($c->name), $needle),
        )), 0, max(1, $limit));
    }

    public function exists(string $id): bool
    {
        return isset($this->companies[$id]);
    }
}
