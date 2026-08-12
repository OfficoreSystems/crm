<?php

declare(strict_types=1);

namespace Crm\Company\UI\Component;

use Crm\Company\Domain\Company;
use Crm\Company\Domain\CompanyRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'CompanyList', template: '@CompanyModule/components/CompanyList.html.twig')]
final class CompanyList
{
    use DefaultActionTrait;

    private const LIMIT = 50;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    public function __construct(
        private readonly CompanyRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<Company>
     */
    public function getCompanies(): array
    {
        return $this->repository->search($this->query, self::LIMIT);
    }

    public function getTotal(): int
    {
        return $this->repository->countAll();
    }

    /**
     * Die drei haeufigsten Branchen als Schnellfilter.
     *
     * @return array<string, int>
     */
    public function getTopIndustries(): array
    {
        return \array_slice($this->repository->countByIndustry(), 0, 3, true);
    }

    public function isFiltered(): bool
    {
        return '' !== trim($this->query);
    }

    public function isTruncated(): bool
    {
        return \count($this->getCompanies()) >= self::LIMIT;
    }
}
