<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Double;

use Crm\Company\Domain\Company;
use Crm\Company\Domain\CompanyRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class InMemoryCompanyRepository implements CompanyRepositoryInterface
{
    /**
     * @var array<string, Company>
     */
    private array $companies = [];

    public function save(Company $company): void
    {
        $this->companies[(string) $company->id()] = $company;
    }

    public function remove(Company $company): void
    {
        unset($this->companies[(string) $company->id()]);
    }

    public function find(Uuid $id): ?Company
    {
        return $this->companies[(string) $id] ?? null;
    }

    public function findByName(string $name): ?Company
    {
        $needle = trim($name);

        foreach ($this->companies as $company) {
            if ($company->name() === $needle) {
                return $company;
            }
        }

        return null;
    }

    public function search(string $query, int $limit = 50): array
    {
        $needle = mb_strtolower(trim($query));

        $matches = array_values(array_filter(
            $this->companies,
            static function (Company $company) use ($needle): bool {
                if ('' === $needle) {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $company->name(),
                    $company->industry(),
                    $company->address()->city,
                ])));

                return str_contains($haystack, $needle);
            },
        ));

        usort($matches, static fn (Company $a, Company $b): int => $a->name() <=> $b->name());

        return \array_slice($matches, 0, max(1, $limit));
    }

    public function findAll(): array
    {
        $all = array_values($this->companies);
        usort($all, static fn (Company $a, Company $b): int => $a->name() <=> $b->name());

        return $all;
    }

    public function countByIndustry(): array
    {
        $counts = [];

        foreach ($this->companies as $company) {
            $industry = $company->industry();

            if (null === $industry) {
                continue;
            }

            $counts[$industry] = ($counts[$industry] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    public function countAll(): int
    {
        return \count($this->companies);
    }
}
