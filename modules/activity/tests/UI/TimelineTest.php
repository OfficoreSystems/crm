<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\UI;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityType;
use Crm\Activity\Tests\Double\InMemoryActivityRepository;
use Crm\Activity\UI\Component\Timeline;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimelineTest extends TestCase
{
    #[Test]
    public function it_lists_entries_newest_first(): void
    {
        $timeline = $this->timeline([
            $this->note('contact', 'a', 'alt', '2026-03-01'),
            $this->note('contact', 'b', 'neu', '2026-03-10'),
        ]);

        $summaries = array_map(static fn (Activity $a): string => $a->summary(), $timeline->getActivities());

        self::assertSame(['neu', 'alt'], $summaries);
    }

    #[Test]
    public function it_resolves_subjects_across_module_types(): void
    {
        $timeline = $this->timeline([
            $this->note('contact', 'a'),
            $this->note('company', 'x'),
        ]);

        $labels = array_map(
            static fn (Activity $a): ?string => $timeline->subjectFor($a)?->label,
            $timeline->getActivities(),
        );

        self::assertContains('Anna Berger', $labels);
        self::assertContains('Nordwind Logistik', $labels);
    }

    #[Test]
    public function an_unresolvable_subject_yields_null_rather_than_failing(): void
    {
        // Der Normalfall nach dem Entfernen eines Moduls oder dem Loeschen
        // des Datensatzes.
        $timeline = $this->timeline([$this->note('invoice', 'z')]);
        $activity = $timeline->getActivities()[0];

        self::assertNull($timeline->subjectFor($activity));
    }

    #[Test]
    public function it_resolves_each_type_in_a_single_lookup(): void
    {
        $contacts = new CountingResolver('contact', 'Kontakt', ['a' => 'Anna', 'b' => 'Bogdan', 'c' => 'Clara']);
        $registry = new SubjectResolverRegistry([$contacts]);

        $timeline = $this->timeline([
            $this->note('contact', 'a'),
            $this->note('contact', 'b'),
            $this->note('contact', 'c'),
        ], $registry);

        foreach ($timeline->getActivities() as $activity) {
            $timeline->subjectFor($activity);
        }

        self::assertSame(1, $contacts->resolveCalls);
    }

    #[Test]
    public function it_filters_by_subject_type(): void
    {
        $timeline = $this->timeline([
            $this->note('contact', 'a'),
            $this->note('company', 'x'),
        ]);
        $timeline->subjectType = 'company';

        self::assertCount(1, $timeline->getActivities());
        self::assertTrue($timeline->isFiltered());
    }

    #[Test]
    public function it_filters_by_activity_type(): void
    {
        $repository = new InMemoryActivityRepository();
        $repository->save(Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'Notiz'));
        $repository->save(Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'Aufgabe'));

        $timeline = new Timeline($repository, $this->registry());
        $timeline->activityType = 'task';

        self::assertCount(1, $timeline->getActivities());
    }

    #[Test]
    public function an_unknown_activity_type_filter_is_ignored(): void
    {
        // tryFrom() liefert null - der Filter faellt weg statt eine Ausnahme
        // auszuloesen, falls jemand die URL von Hand bastelt.
        $timeline = $this->timeline([$this->note('contact', 'a')]);
        $timeline->activityType = 'gibtsnicht';

        self::assertCount(1, $timeline->getActivities());
    }

    #[Test]
    public function without_filters_it_is_not_filtered(): void
    {
        self::assertFalse($this->timeline([])->isFiltered());
    }

    #[Test]
    public function it_offers_exactly_the_registered_subject_types(): void
    {
        // Waechst und schrumpft mit den installierten Modulen.
        $timeline = $this->timeline([]);

        self::assertSame(['company' => 'Firma', 'contact' => 'Kontakt'], $timeline->getSubjectTypes());
        self::assertSame([], $this->timeline([], new SubjectResolverRegistry([]))->getSubjectTypes());
    }

    #[Test]
    public function it_counts_open_tasks(): void
    {
        $repository = new InMemoryActivityRepository();
        $repository->save(Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'offen'));
        $done = Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'erledigt');
        $done->complete();
        $repository->save($done);
        $repository->save(Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'Notiz'));

        $timeline = new Timeline($repository, $this->registry());

        self::assertSame(3, $timeline->getTotal());
        self::assertSame(1, $timeline->getOpenTasks());
    }

    /**
     * @param list<Activity> $activities
     */
    private function timeline(array $activities, ?SubjectResolverRegistry $registry = null): Timeline
    {
        $repository = new InMemoryActivityRepository();

        foreach ($activities as $activity) {
            $repository->save($activity);
        }

        return new Timeline($repository, $registry ?? $this->registry());
    }

    private function note(string $type, string $id, string $summary = 'Notiz', ?string $at = null): Activity
    {
        return Activity::log(
            ActivityType::NOTE,
            SubjectRef::of($type, $id),
            $summary,
            occurredAt: null === $at ? null : new \DateTimeImmutable($at),
        );
    }

    private function registry(): SubjectResolverRegistry
    {
        return new SubjectResolverRegistry([
            new CountingResolver('contact', 'Kontakt', ['a' => 'Anna Berger', 'b' => 'Bogdan Petrov', 'c' => 'Clara Dupont']),
            new CountingResolver('company', 'Firma', ['x' => 'Nordwind Logistik']),
        ]);
    }
}

final class CountingResolver implements SubjectResolverInterface
{
    public int $resolveCalls = 0;

    /**
     * @param array<string, string> $labelsById
     */
    public function __construct(
        private readonly string $type,
        private readonly string $typeLabel,
        private readonly array $labelsById,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function typeLabel(): string
    {
        return $this->typeLabel;
    }

    public function resolve(array $ids): array
    {
        ++$this->resolveCalls;

        $resolved = [];

        foreach ($ids as $id) {
            if (isset($this->labelsById[$id])) {
                $resolved[$id] = new ResolvedSubject($this->type, $id, $this->labelsById[$id], typeLabel: $this->typeLabel);
            }
        }

        return $resolved;
    }

    public function search(string $query, int $limit = 10): array
    {
        $found = [];

        foreach ($this->labelsById as $id => $label) {
            $found[] = new ResolvedSubject($this->type, (string) $id, $label, typeLabel: $this->typeLabel);
        }

        return \array_slice($found, 0, max(1, $limit));
    }
}
