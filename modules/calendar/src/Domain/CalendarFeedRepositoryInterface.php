<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

use Symfony\Component\Uid\Uuid;

interface CalendarFeedRepositoryInterface
{
    public function save(CalendarFeed $feed): void;

    public function findForUser(Uuid $userId): ?CalendarFeed;

    /**
     * Sucht nach dem Hash, nie nach dem Klartext-Token.
     */
    public function findByTokenHash(string $tokenHash): ?CalendarFeed;
}
