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
            label: 'Offene Aufgaben',
            value: (string) $open,
            description: 0 === $overdue ? 'nichts ueberfaellig' : sprintf('davon %d ueberfaellig', $overdue),
            route: 'activity_index',
            routeParameters: ['activityType' => 'task'],
            priority: 95,
            // Der einzige Wert auf der Startseite, der zum Handeln auffordert.
            tone: $overdue > 0 ? MetricTone::ATTENTION : MetricTone::NEUTRAL,
        );

        yield new Metric(
            key: 'activity.total',
            label: 'Aktivitäten',
            value: (string) $this->activities->countAll(),
            description: 'insgesamt erfasst',
            route: 'activity_index',
            priority: 40,
        );
    }
}
