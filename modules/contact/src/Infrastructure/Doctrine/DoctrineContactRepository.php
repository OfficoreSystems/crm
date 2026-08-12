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

    public function search(string $query, array $companyIds = [], int $limit = 50): array
    {
        $builder = $this->baseQuery($limit);

        $needle = trim($query);

        if ('' === $needle) {
            return $builder->getQuery()->getResult();
        }

        $matchesOwnFields = $builder->expr()->orX(
            'LOWER(c.firstName) LIKE :needle',
            'LOWER(c.lastName) LIKE :needle',
            'LOWER(c.email) LIKE :needle',
        );

        if ([] === $companyIds) {
            $builder->andWhere($matchesOwnFields);
        } else {
            // ODER, nicht UND: wer "Nordwind" tippt, will die Kontakte dieser
            // Firma sehen - auch wenn keiner von ihnen so heisst.
            $builder
                ->andWhere($builder->expr()->orX($matchesOwnFields, 'c.companyId IN (:companyIds)'))
                ->setParameter('companyIds', $companyIds);
        }

        // Wildcards im Suchbegriff escapen, sonst wird "%" zur Volltextsuche
        // und "_" zum Einzelzeichen-Joker.
        $builder->setParameter('needle', '%'.addcslashes(mb_strtolower($needle), '%_\\').'%');

        return $builder->getQuery()->getResult();
    }

    public function findByCompanyIds(array $companyIds, int $limit = 50): array
    {
        if ([] === $companyIds) {
            return [];
        }

        return $this->baseQuery($limit)
            ->andWhere('c.companyId IN (:companyIds)')
            ->setParameter('companyIds', $companyIds)
            ->getQuery()
            ->getResult();
    }

    public function countByCompanyId(string $companyId): int
    {
        if (!Uuid::isValid($companyId)) {
            return 0;
        }

        /** @var int|string $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->andWhere('c.companyId = :companyId')
            ->setParameter('companyId', Uuid::fromString($companyId), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    private function baseQuery(int $limit): \Doctrine\ORM\QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->setMaxResults(max(1, $limit));
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
