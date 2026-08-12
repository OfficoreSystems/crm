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
            label: 'Firmen',
            value: (string) $this->companies->countAll(),
            description: [] === $byIndustry
                ? null
                : sprintf('%d Branchen, groesste: %s', \count($byIndustry), array_key_first($byIndustry)),
            route: 'company_index',
            priority: 50,
        );
    }
}
