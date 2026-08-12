<?php

declare(strict_types=1);

namespace Crm\User\Infrastructure\Doctrine;

use Crm\User\Domain\User;
use Crm\User\Domain\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function remove(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => self::normalize($email)]);
    }

    public function emailExists(string $email): bool
    {
        return null !== $this->findByEmail($email);
    }

    public function search(string $query, int $limit = 50): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults(max(1, $limit));

        $needle = trim($query);

        if ('' !== $needle) {
            $builder
                ->andWhere($builder->expr()->orX(
                    'LOWER(u.name) LIKE :needle',
                    'LOWER(u.email) LIKE :needle',
                ))
                ->setParameter('needle', '%'.addcslashes(mb_strtolower($needle), '%_\\').'%');
        }

        return $builder->getQuery()->getResult();
    }

    public function findAllActive(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->andWhere('u.active = true')
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * Adressen liegen normalisiert in der Datenbank. Die Suche muss dieselbe
     * Normalisierung anwenden, sonst findet " A@X.de " den Datensatz nicht.
     */
    private static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
