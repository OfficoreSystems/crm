<?php

declare(strict_types=1);

namespace Crm\Calendar\Infrastructure\SharedKernel;

use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;
use Crm\SharedKernel\Dashboard\MetricTone;

final readonly class AppointmentMetricProvider implements MetricProviderInterface
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
    ) {
    }

    public function getMetrics(): iterable
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $upcoming = $this->appointments->findUpcoming($now, 50);
        $today = array_filter(
            $upcoming,
            static fn ($a): bool => $a->startsAt() < $now->modify('tomorrow'),
        );

        yield new Metric(
            key: 'calendar.upcoming',
            label: 'Termine',
            value: (string) \count($upcoming),
            description: 0 === \count($today)
                ? 'heute nichts mehr'
                : sprintf('%d davon heute', \count($today)),
            route: 'calendar_index',
            priority: 80,
            // Was heute noch ansteht, verdient einen Blick - alles andere
            // hat Zeit.
            tone: [] === $today ? MetricTone::NEUTRAL : MetricTone::POSITIVE,
        );
    }
}
