<?php

declare(strict_types=1);

namespace Crm\Activity\Infrastructure\Doctrine;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\Activity\Domain\ActivityType;
use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineActivityRepository implements ActivityRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Activity $activity): void
    {
        $this->entityManager->persist($activity);
        $this->entityManager->flush();
    }

    public function remove(Activity $activity): void
    {
        $this->entityManager->remove($activity);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Activity
    {
        return $this->entityManager->find(Activity::class, $id);
    }

    public function findForSubject(SubjectRef $subject, int $limit = 50): array
    {
        return $this->newestFirst($limit)
            ->andWhere('a.subjectType = :type')
            ->andWhere('a.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->getResult();
    }

    public function findRecent(?string $subjectType = null, ?ActivityType $type = null, int $limit = 50): array
    {
        $builder = $this->newestFirst($limit);

        if (null !== $subjectType) {
            $builder->andWhere('a.subjectType = :subjectType')->setParameter('subjectType', $subjectType);
        }

        if (null !== $type) {
            $builder->andWhere('a.type = :type')->setParameter('type', $type->value);
        }

        return $builder->getQuery()->getResult();
    }

    public function findOverdueTasks(\DateTimeImmutable $now, int $limit = 50): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Activity::class, 'a')
            ->andWhere('a.type = :task')
            ->andWhere('a.completedAt IS NULL')
            ->andWhere('a.occurredAt < :now')
            // Aelteste zuerst: was am laengsten liegt, draengt am meisten.
            ->orderBy('a.occurredAt', 'ASC')
            ->setParameter('task', ActivityType::TASK->value)
            ->setParameter('now', $now)
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function countForSubject(SubjectRef $subject): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Activity::class, 'a')
            ->andWhere('a.subjectType = :type')
            ->andWhere('a.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function countAll(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Activity::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function countOpenTasks(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Activity::class, 'a')
            ->andWhere('a.type = :task')
            ->andWhere('a.completedAt IS NULL')
            ->setParameter('task', ActivityType::TASK->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    private function newestFirst(int $limit): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Activity::class, 'a')
            ->orderBy('a.occurredAt', 'DESC')
            // Zweites Kriterium, damit gleichzeitige Eintraege eine stabile
            // Reihenfolge haben - v7-UUIDs sind zeitgeordnet.
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(max(1, $limit));
    }
}
