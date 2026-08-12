<?php

declare(strict_types=1);

namespace Crm\Contact\Infrastructure\SharedKernel;

use Crm\Contact\Domain\ContactRepositoryInterface;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;

final readonly class ContactMetricProvider implements MetricProviderInterface
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
    ) {
    }

    public function getMetrics(): iterable
    {
        yield new Metric(
            key: 'contact.total',
            label: 'Kontakte',
            value: (string) $this->contacts->countAll(),
            route: 'contact_index',
            priority: 60,
        );
    }
}
