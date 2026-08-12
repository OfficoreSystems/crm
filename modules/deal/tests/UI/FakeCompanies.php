<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\UI;

use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;

/**
 * Steht im Test fuer das company-Modul und zaehlt die Aufrufe, um ein N+1
 * ueber die Modulgrenze nachweisbar zu machen.
 */
final class FakeCompanies implements CompanyFinderInterface
{
    public int $findManyCalls = 0;

    /**
     * @var array<string, CompanySummary>
     */
    private array $companies = [];

    /**
     * @param list<CompanySummary> $companies
     */
    public function __construct(array $companies)
    {
        foreach ($companies as $company) {
            $this->companies[$company->id] = $company;
        }
    }

    public function find(string $id): ?CompanySummary
    {
        return $this->companies[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        ++$this->findManyCalls;

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

        return array_values(array_filter(
            $this->companies,
            static fn (CompanySummary $c): bool => str_contains(mb_strtolower($c->name), $needle),
        ));
    }

    public function exists(string $id): bool
    {
        return isset($this->companies[$id]);
    }
}
