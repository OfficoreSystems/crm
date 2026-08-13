<?php

declare(strict_types=1);

namespace Crm\Calendar\Infrastructure\Doctrine;

use Crm\Calendar\Domain\CalendarFeed;
use Crm\Calendar\Domain\CalendarFeedRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCalendarFeedRepository implements CalendarFeedRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CalendarFeed $feed): void
    {
        $this->entityManager->persist($feed);
        $this->entityManager->flush();
    }

    public function findForUser(Uuid $userId): ?CalendarFeed
    {
        return $this->entityManager->getRepository(CalendarFeed::class)
            ->findOneBy(['userId' => $userId]);
    }

    public function findByTokenHash(string $tokenHash): ?CalendarFeed
    {
        return $this->entityManager->getRepository(CalendarFeed::class)
            ->findOneBy(['tokenHash' => $tokenHash]);
    }
}
