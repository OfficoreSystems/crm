<?php

declare(strict_types=1);

namespace Crm\User\Infrastructure\Doctrine;

use Crm\User\Domain\Team;
use Crm\User\Domain\TeamRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineTeamRepository implements TeamRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Team $team): void
    {
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }

    public function remove(Team $team): void
    {
        $this->entityManager->remove($team);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Team
    {
        return $this->entityManager->find(Team::class, $id);
    }

    public function findByName(string $name): ?Team
    {
        return $this->entityManager->getRepository(Team::class)
            ->findOneBy(['name' => trim($name)]);
    }

    public function findAll(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Team::class, 't')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
