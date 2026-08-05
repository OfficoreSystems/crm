<?php

declare(strict_types=1);

namespace Crm\Contact\Infrastructure\Doctrine;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineContactRepository implements ContactRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Contact $contact): void
    {
        $this->entityManager->persist($contact);
        $this->entityManager->flush();
    }

    public function remove(Contact $contact): void
    {
        $this->entityManager->remove($contact);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Contact
    {
        return $this->entityManager->find(Contact::class, $id);
    }

    public function search(string $query, int $limit = 50): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->setMaxResults(max(1, $limit));

        $needle = trim($query);

        if ('' !== $needle) {
            $builder
                ->andWhere($builder->expr()->orX(
                    'LOWER(c.firstName) LIKE :needle',
                    'LOWER(c.lastName) LIKE :needle',
                    'LOWER(c.email) LIKE :needle',
                    'LOWER(c.company) LIKE :needle',
                ))
                // Wildcards im Suchbegriff escapen, sonst wird "%" zur Volltextsuche
                // und "_" zum Einzelzeichen-Joker.
                ->setParameter('needle', '%'.addcslashes(mb_strtolower($needle), '%_\\').'%');
        }

        return $builder->getQuery()->getResult();
    }

    public function countAll(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
