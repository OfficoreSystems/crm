<?php

declare(strict_types=1);

namespace Crm\Company\Infrastructure\SharedKernel;

use Crm\Company\Domain\CompanyRepositoryInterface;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;

final readonly class CompanyMetricProvider implements MetricProviderInterface
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {
    }

    public function getMetrics(): iterable
    {
        $byIndustry = $this->companies->countByIndustry();

        yield new Metric(
            key: 'company.total',
            label: 'company.metric.label',
            value: (string) $this->companies->countAll(),
            description: [] === $byIndustry ? 'company.metric.none_yet' : 'company.metric.by_industry',
            parameters: ['%industry%' => [] === $byIndustry ? '' : (string) array_key_first($byIndustry)],
            route: 'company_index',
            priority: 50,
        );
    }
}
