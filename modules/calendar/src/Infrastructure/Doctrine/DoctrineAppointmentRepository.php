<?php

declare(strict_types=1);

namespace Crm\Calendar\Infrastructure\Doctrine;

use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Appointment $appointment): void
    {
        $this->entityManager->persist($appointment);
        $this->entityManager->flush();
    }

    public function remove(Appointment $appointment): void
    {
        $this->entityManager->remove($appointment);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Appointment
    {
        return $this->entityManager->find(Appointment::class, $id);
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $until, int $limit = 500): array
    {
        // Ueberschneidung, nicht Enthaltensein: ein Termin von Freitag bis
        // Montag gehoert in die Wochenansicht beider Wochen. Mit
        // "starts_at BETWEEN" fiele er aus der zweiten heraus.
        return $this->chronological($limit)
            ->andWhere('a.startsAt < :until')
            ->andWhere('a.endsAt > :from')
            ->setParameter('from', $this->utc($from))
            ->setParameter('until', $this->utc($until))
            ->getQuery()
            ->getResult();
    }

    public function findUpcoming(\DateTimeImmutable $from, int $limit = 50): array
    {
        return $this->chronological($limit)
            ->andWhere('a.endsAt > :from')
            ->setParameter('from', $this->utc($from))
            ->getQuery()
            ->getResult();
    }

    public function findForSubject(SubjectRef $subject, int $limit = 50): array
    {
        return $this->chronological($limit)
            ->andWhere('a.subjectType = :type')
            ->andWhere('a.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->getResult();
    }

    public function findForOwnerBetween(
        Uuid $ownerId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
        int $limit = 1000,
    ): array {
        return $this->chronological($limit)
            ->andWhere('a.ownerId = :owner')
            ->andWhere('a.startsAt < :until')
            ->andWhere('a.endsAt > :from')
            ->setParameter('owner', $ownerId, 'uuid')
            ->setParameter('from', $this->utc($from))
            ->setParameter('until', $this->utc($until))
            ->getQuery()
            ->getResult();
    }

    public function countUpcoming(\DateTimeImmutable $from): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Appointment::class, 'a')
            ->andWhere('a.endsAt > :from')
            ->setParameter('from', $this->utc($from))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Appointment::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function chronological(int $limit): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            // Aelteste zuerst: ein Kalender liest sich vorwaerts.
            ->orderBy('a.startsAt', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->setMaxResults(max(1, $limit));
    }

    /**
     * Vergleichswerte ebenfalls in UTC.
     *
     * Kaeme hier eine Berliner Zeit an, waere der Vergleich um zwei Stunden
     * verschoben - und im Sommer anders als im Winter.
     */
    private function utc(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'));
    }
}
