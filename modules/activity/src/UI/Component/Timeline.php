<?php

declare(strict_types=1);

namespace Crm\Activity\UI\Component;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\Activity\Domain\ActivityType;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Die globale Timeline.
 *
 * Die Subjekte werden in einem Rutsch aufgeloest: die Registry gruppiert nach
 * Typ und ruft jeden Resolver genau einmal. Fuenfzig Eintraege ueber drei
 * Module kosten drei Aufrufe, nicht fuenfzig.
 */
#[AsLiveComponent(name: 'Timeline', template: '@ActivityModule/components/Timeline.html.twig')]
final class Timeline
{
    use DefaultActionTrait;

    private const LIMIT = 50;

    #[LiveProp(writable: true, url: true)]
    public string $subjectType = '';

    #[LiveProp(writable: true, url: true)]
    public string $activityType = '';

    /**
     * @var list<Activity>|null
     */
    private ?array $cachedActivities = null;

    /**
     * @var array<string, ResolvedSubject>|null
     */
    private ?array $cachedSubjects = null;

    public function __construct(
        private readonly ActivityRepositoryInterface $repository,
        private readonly SubjectResolverRegistry $subjects,
    ) {
    }

    /**
     * @return list<Activity>
     */
    public function getActivities(): array
    {
        if (null !== $this->cachedActivities) {
            return $this->cachedActivities;
        }

        return $this->cachedActivities = $this->repository->findRecent(
            '' === $this->subjectType ? null : $this->subjectType,
            ActivityType::tryFrom($this->activityType),
            self::LIMIT,
        );
    }

    /**
     * Die Typen, fuer die aktuell ein Resolver registriert ist.
     *
     * Waechst und schrumpft mit den installierten Modulen - deshalb hier
     * abgefragt statt fest verdrahtet.
     *
     * @return array<string, string>
     */
    public function getSubjectTypes(): array
    {
        return $this->subjects->supportedTypes();
    }

    /**
     * @return list<ActivityType>
     */
    public function getActivityTypes(): array
    {
        return ActivityType::cases();
    }

    public function subjectFor(Activity $activity): ?ResolvedSubject
    {
        return $this->getSubjects()[$activity->subject()->key()] ?? null;
    }

    public function getTotal(): int
    {
        return $this->repository->countAll();
    }

    public function getOpenTasks(): int
    {
        return $this->repository->countOpenTasks();
    }

    public function isFiltered(): bool
    {
        return '' !== $this->subjectType || '' !== $this->activityType;
    }

    /**
     * @return array<string, ResolvedSubject>
     */
    private function getSubjects(): array
    {
        if (null !== $this->cachedSubjects) {
            return $this->cachedSubjects;
        }

        $refs = array_map(
            static fn (Activity $activity) => $activity->subject(),
            $this->getActivities(),
        );

        return $this->cachedSubjects = [] === $refs ? [] : $this->subjects->resolveAll($refs);
    }
}
