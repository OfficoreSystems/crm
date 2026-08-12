<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\Infrastructure;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityType;
use Crm\Activity\Infrastructure\SharedKernel\ActivityMetricProvider;
use Crm\Activity\Tests\Double\InMemoryActivityRepository;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricTone;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivityMetricProviderTest extends TestCase
{
    #[Test]
    public function overdue_tasks_demand_attention(): void
    {
        // Der einzige Wert auf der Startseite, der zum Handeln auffordert.
        $metrics = $this->metricsFor([
            $this->task('faellig', '2020-01-01'),
            $this->task('spaeter', '2099-01-01'),
        ]);

        self::assertSame('2', $metrics['activity.open_tasks']->value);
        self::assertSame('davon 1 ueberfaellig', $metrics['activity.open_tasks']->description);
        self::assertSame(MetricTone::ATTENTION, $metrics['activity.open_tasks']->tone);
    }

    #[Test]
    public function without_anything_overdue_it_stays_neutral(): void
    {
        $metrics = $this->metricsFor([$this->task('spaeter', '2099-01-01')]);

        self::assertSame(MetricTone::NEUTRAL, $metrics['activity.open_tasks']->tone);
        self::assertSame('nichts ueberfaellig', $metrics['activity.open_tasks']->description);
    }

    #[Test]
    public function completed_tasks_count_neither_as_open_nor_as_overdue(): void
    {
        $done = $this->task('erledigt', '2020-01-01');
        $done->complete();

        $metrics = $this->metricsFor([$done]);

        self::assertSame('0', $metrics['activity.open_tasks']->value);
        self::assertSame(MetricTone::NEUTRAL, $metrics['activity.open_tasks']->tone);
    }

    #[Test]
    public function notes_are_not_tasks(): void
    {
        $metrics = $this->metricsFor([
            Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'Notiz'),
        ]);

        self::assertSame('0', $metrics['activity.open_tasks']->value);
        self::assertSame('1', $metrics['activity.total']->value);
    }

    #[Test]
    public function the_open_tasks_metric_links_to_the_filtered_timeline(): void
    {
        $metric = $this->metricsFor([])['activity.open_tasks'];

        self::assertTrue($metric->isLinkable());
        self::assertSame(['activityType' => 'task'], $metric->routeParameters);
    }

    /**
     * @param list<Activity> $activities
     *
     * @return array<string, Metric>
     */
    private function metricsFor(array $activities): array
    {
        $repository = new InMemoryActivityRepository();

        foreach ($activities as $activity) {
            $repository->save($activity);
        }

        $metrics = [];

        foreach ((new ActivityMetricProvider($repository))->getMetrics() as $metric) {
            $metrics[$metric->key] = $metric;
        }

        return $metrics;
    }

    private function task(string $summary, string $occurredAt): Activity
    {
        return Activity::log(
            ActivityType::TASK,
            SubjectRef::of('contact', 'a'),
            $summary,
            occurredAt: new \DateTimeImmutable($occurredAt),
        );
    }
}
