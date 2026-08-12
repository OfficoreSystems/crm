<?php

declare(strict_types=1);

namespace Crm\Activity\Domain;

use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

interface ActivityRepositoryInterface
{
    public function save(Activity $activity): void;

    public function remove(Activity $activity): void;

    public function find(Uuid $id): ?Activity;

    /**
     * Die Timeline eines einzelnen Datensatzes.
     *
     * @return list<Activity>
     */
    public function findForSubject(SubjectRef $subject, int $limit = 50): array;

    /**
     * Die globale Timeline, neueste zuerst.
     *
     * @param string|null $subjectType Auf einen Typ einschraenken.
     *
     * @return list<Activity>
     */
    public function findRecent(?string $subjectType = null, ?ActivityType $type = null, int $limit = 50): array;

    /**
     * Offene Aufgaben, deren Zeitpunkt in der Vergangenheit liegt.
     *
     * @return list<Activity>
     */
    public function findOverdueTasks(\DateTimeImmutable $now, int $limit = 50): array;

    public function countForSubject(SubjectRef $subject): int;

    public function countAll(): int;

    public function countOpenTasks(): int;
}
