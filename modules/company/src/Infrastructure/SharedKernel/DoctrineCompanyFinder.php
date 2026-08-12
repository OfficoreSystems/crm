<?php

declare(strict_types=1);

namespace Crm\Company\Infrastructure\SharedKernel;

use Crm\Company\Domain\Company;
use Crm\Company\Domain\CompanyRepositoryInterface;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;
use Symfony\Component\Uid\Uuid;

/**
 * Bedient den Extension-Point des Shared Kernel.
 *
 * Gibt nie eine Entity nach draussen - nur {@see CompanySummary}.
 */
final readonly class DoctrineCompanyFinder implements CompanyFinderInterface
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {
    }

    public function find(string $id): ?CompanySummary
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $company = $this->companies->find(Uuid::fromString($id));

        return null === $company ? null : self::toSummary($company);
    }

    public function findMany(array $ids): array
    {
        $summaries = [];

        foreach ($ids as $id) {
            $summary = $this->find($id);

            if (null !== $summary) {
                $summaries[$id] = $summary;
            }
        }

        return $summaries;
    }

    public function findAll(): array
    {
        return array_map(self::toSummary(...), $this->companies->findAll());
    }

    public function searchByName(string $query, int $limit = 25): array
    {
        if ('' === trim($query)) {
            return [];
        }

        return array_map(self::toSummary(...), $this->companies->search($query, $limit));
    }

    public function exists(string $id): bool
    {
        return null !== $this->find($id);
    }

    private static function toSummary(Company $company): CompanySummary
    {
        return new CompanySummary(
            id: (string) $company->id(),
            name: $company->name(),
            industry: $company->industry(),
            city: $company->address()->city,
        );
    }
}
