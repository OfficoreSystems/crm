<?php

declare(strict_types=1);

namespace Crm\Company\Infrastructure\SharedKernel;

use Crm\Company\Domain\Company;
use Crm\Company\Domain\CompanyRepositoryInterface;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CompanySubjectResolver implements SubjectResolverInterface
{
    public const TYPE = 'company';

    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function typeLabel(): string
    {
        return 'company.subject_type';
    }

    public function resolve(array $ids): array
    {
        $resolved = [];

        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                continue;
            }

            $company = $this->companies->find(Uuid::fromString($id));

            if (null === $company) {
                continue;
            }

            $resolved[$id] = self::toSubject($company);
        }

        return $resolved;
    }

    public function search(string $query, int $limit = 10): array
    {
        return array_map(self::toSubject(...), $this->companies->search($query, $limit));
    }

    private static function toSubject(Company $company): ResolvedSubject
    {
        $parts = array_filter([$company->industry(), $company->address()->city]);

        return new ResolvedSubject(
            type: self::TYPE,
            id: (string) $company->id(),
            label: $company->name(),
            route: 'company_index',
            typeLabel: 'company.subject_type',
            description: [] === $parts ? null : implode(' · ', $parts),
        );
    }
}
