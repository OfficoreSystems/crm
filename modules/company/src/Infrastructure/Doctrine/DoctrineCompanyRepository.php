<?php

declare(strict_types=1);

namespace Crm\Company\Infrastructure\Doctrine;

use Crm\Company\Domain\Company;
use Crm\Company\Domain\CompanyRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCompanyRepository implements CompanyRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Company $company): void
    {
        $this->entityManager->persist($company);
        $this->entityManager->flush();
    }

    public function remove(Company $company): void
    {
        $this->entityManager->remove($company);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Company
    {
        return $this->entityManager->find(Company::class, $id);
    }

    public function findByName(string $name): ?Company
    {
        return $this->entityManager->getRepository(Company::class)
            ->findOneBy(['name' => trim($name)]);
    }

    public function search(string $query, int $limit = 50): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Company::class, 'c')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(max(1, $limit));

        $needle = trim($query);

        if ('' !== $needle) {
            $builder
                ->andWhere($builder->expr()->orX(
                    'LOWER(c.name) LIKE :needle',
                    'LOWER(c.industry) LIKE :needle',
                    'LOWER(c.address.city) LIKE :needle',
                ))
                ->setParameter('needle', '%'.addcslashes(mb_strtolower($needle), '%_\\').'%');
        }

        return $builder->getQuery()->getResult();
    }

    public function findAll(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Company::class, 'c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByIndustry(): array
    {
        /** @var list<array{industry: string|null, total: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('c.industry AS industry', 'COUNT(c.id) AS total')
            ->from(Company::class, 'c')
            ->andWhere('c.industry IS NOT NULL')
            ->groupBy('c.industry')
            ->orderBy('total', 'DESC')
            ->addOrderBy('c.industry', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];

        foreach ($rows as $row) {
            if (null !== $row['industry']) {
                $counts[$row['industry']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public function countAll(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Company::class, 'c')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
