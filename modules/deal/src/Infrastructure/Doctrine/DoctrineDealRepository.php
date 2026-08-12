<?php

declare(strict_types=1);

namespace Crm\Deal\Infrastructure\Doctrine;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Domain\Stage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineDealRepository implements DealRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Deal $deal): void
    {
        $this->entityManager->persist($deal);
        $this->entityManager->flush();
    }

    public function remove(Deal $deal): void
    {
        $this->entityManager->remove($deal);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Deal
    {
        return $this->entityManager->find(Deal::class, $id);
    }

    public function search(string $query, array $companyIds = [], array $contactIds = [], int $limit = 100): array
    {
        $builder = $this->baseQuery($limit);
        $needle = trim($query);

        if ('' === $needle) {
            return $builder->getQuery()->getResult();
        }

        $conditions = [$builder->expr()->like('LOWER(d.title)', ':needle')];

        if ([] !== $companyIds) {
            $conditions[] = 'd.companyId IN (:companyIds)';
            $builder->setParameter('companyIds', $companyIds);
        }

        if ([] !== $contactIds) {
            $conditions[] = 'd.contactId IN (:contactIds)';
            $builder->setParameter('contactIds', $contactIds);
        }

        return $builder
            ->andWhere($builder->expr()->orX(...$conditions))
            ->setParameter('needle', '%'.addcslashes(mb_strtolower($needle), '%_\\').'%')
            ->getQuery()
            ->getResult();
    }

    public function findByStage(Stage $stage, int $limit = 100): array
    {
        return $this->baseQuery($limit)
            ->andWhere('d.stage = :stage')
            ->setParameter('stage', $stage->value)
            ->getQuery()
            ->getResult();
    }

    public function statsByStage(): array
    {
        /** @var list<array{stage: Stage|string, total: int|string, cents: int|string|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('d.stage AS stage', 'COUNT(d.id) AS total', 'SUM(d.value.amount) AS cents')
            ->from(Deal::class, 'd')
            ->groupBy('d.stage')
            ->getQuery()
            ->getResult();

        $stats = [];

        foreach ($rows as $row) {
            $stage = $row['stage'] instanceof Stage ? $row['stage']->value : (string) $row['stage'];

            $stats[$stage] = [
                'count' => (int) $row['total'],
                // SUM() liefert NULL, wenn keine Zeile passt - hier kann das
                // nicht auftreten, aber der Cast macht den Typ eindeutig.
                'cents' => (int) ($row['cents'] ?? 0),
            ];
        }

        return $stats;
    }

    public function countAll(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Deal::class, 'd')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function countByStage(Stage $stage): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Deal::class, 'd')
            ->andWhere('d.stage = :stage')
            ->setParameter('stage', $stage->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    private function baseQuery(int $limit): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Deal::class, 'd')
            ->orderBy('d.value.amount', 'DESC')
            ->addOrderBy('d.title', 'ASC')
            ->setMaxResults(max(1, $limit));
    }
}
