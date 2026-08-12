<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\Double;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\Activity\Domain\ActivityType;
use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

final class InMemoryActivityRepository implements ActivityRepositoryInterface
{
    /**
     * @var array<string, Activity>
     */
    private array $activities = [];

    public function save(Activity $activity): void
    {
        $this->activities[(string) $activity->id()] = $activity;
    }

    public function remove(Activity $activity): void
    {
        unset($this->activities[(string) $activity->id()]);
    }

    public function find(Uuid $id): ?Activity
    {
        return $this->activities[(string) $id] ?? null;
    }

    public function findForSubject(SubjectRef $subject, int $limit = 50): array
    {
        $matches = array_values(array_filter(
            $this->activities,
            static fn (Activity $a): bool => $a->subject()->equals($subject),
        ));

        return \array_slice(self::newestFirst($matches), 0, max(1, $limit));
    }

    public function findRecent(?string $subjectType = null, ?ActivityType $type = null, int $limit = 50): array
    {
        $matches = array_values(array_filter(
            $this->activities,
            static function (Activity $a) use ($subjectType, $type): bool {
                if (null !== $subjectType && $a->subject()->type !== $subjectType) {
                    return false;
                }

                return null === $type || $a->type() === $type;
            },
        ));

        return \array_slice(self::newestFirst($matches), 0, max(1, $limit));
    }

    public function findOverdueTasks(\DateTimeImmutable $now, int $limit = 50): array
    {
        $matches = array_values(array_filter(
            $this->activities,
            static fn (Activity $a): bool => $a->isOverdue($now),
        ));

        usort($matches, static fn (Activity $a, Activity $b): int => $a->occurredAt() <=> $b->occurredAt());

        return \array_slice($matches, 0, max(1, $limit));
    }

    public function countForSubject(SubjectRef $subject): int
    {
        return \count(array_filter(
            $this->activities,
            static fn (Activity $a): bool => $a->subject()->equals($subject),
        ));
    }

    public function countAll(): int
    {
        return \count($this->activities);
    }

    public function countOpenTasks(): int
    {
        return \count(array_filter(
            $this->activities,
            static fn (Activity $a): bool => $a->isOpenTask(),
        ));
    }

    /**
     * @param list<Activity> $activities
     *
     * @return list<Activity>
     */
    private static function newestFirst(array $activities): array
    {
        usort($activities, static fn (Activity $a, Activity $b): int => $b->occurredAt() <=> $a->occurredAt());

        return $activities;
    }
}
