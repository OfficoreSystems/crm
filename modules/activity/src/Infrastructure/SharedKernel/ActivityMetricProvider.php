<?php

declare(strict_types=1);

namespace Crm\Activity\Infrastructure\SharedKernel;

use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;
use Crm\SharedKernel\Dashboard\MetricTone;

final readonly class ActivityMetricProvider implements MetricProviderInterface
{
    public function __construct(
        private ActivityRepositoryInterface $activities,
    ) {
    }

    public function getMetrics(): iterable
    {
        $open = $this->activities->countOpenTasks();
        $overdue = \count($this->activities->findOverdueTasks(new \DateTimeImmutable()));

        yield new Metric(
            key: 'activity.open_tasks',
            label: 'activity.metric.open_tasks',
            value: (string) $open,
            description: 'activity.metric.overdue',
            route: 'activity_index',
            routeParameters: ['activityType' => 'task'],
            parameters: ['%count%' => $overdue],
            priority: 95,
            // Der einzige Wert auf der Startseite, der zum Handeln auffordert.
            tone: $overdue > 0 ? MetricTone::ATTENTION : MetricTone::NEUTRAL,
        );

        yield new Metric(
            key: 'activity.total',
            label: 'activity.metric.total',
            value: (string) $this->activities->countAll(),
            description: 'activity.metric.total_description',
            route: 'activity_index',
            priority: 40,
        );
    }
}
