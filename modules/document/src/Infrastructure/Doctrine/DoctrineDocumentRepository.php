<?php

declare(strict_types=1);

namespace Crm\Document\Infrastructure\Doctrine;

use Crm\Document\Domain\Document;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineDocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Document $document): void
    {
        $this->entityManager->persist($document);
        $this->entityManager->flush();
    }

    public function remove(Document $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Document
    {
        return $this->entityManager->find(Document::class, $id);
    }

    public function findForSubject(SubjectRef $subject, int $limit = 100): array
    {
        return $this->newestFirst($limit)
            ->andWhere('d.subjectType = :type')
            ->andWhere('d.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->newestFirst($limit)->getQuery()->getResult();
    }

    public function countForSubject(SubjectRef $subject): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->andWhere('d.subjectType = :type')
            ->andWhere('d.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function totalBytes(): int
    {
        // In der Datenbank summiert, nicht in PHP: bei zehntausend Dokumenten
        // waere das Laden aller Zeilen fuer eine einzige Zahl absurd.
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(d.size), 0)')
            ->from(Document::class, 'd')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function newestFirst(int $limit): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->orderBy('d.uploadedAt', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(max(1, $limit));
    }
}
