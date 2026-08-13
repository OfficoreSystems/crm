<?php

declare(strict_types=1);

namespace Crm\Calendar\Application;

use Crm\Calendar\Domain\CalendarFeed;
use Crm\Calendar\Domain\CalendarFeedRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Use-Case: die persoenliche Abonnement-URL besorgen.
 *
 * Das Klartext-Token gibt es nur hier und nur einmal - gespeichert ist der
 * Hash. Wer die URL verliert, laesst eine neue erzeugen; die alte ist damit
 * sofort wertlos.
 *
 * Genau deshalb liefert diese Klasse beim zweiten Aufruf *kein* Token mehr,
 * sondern null: haette man es weiterhin bekommen, waere der Hash im Speicher
 * kein Schutz, sondern Dekoration.
 */
final readonly class SubscribeToCalendar
{
    public function __construct(
        private CalendarFeedRepositoryInterface $feeds,
    ) {
    }

    /**
     * @return array{0: CalendarFeed, 1: string|null} Feed und - nur beim
     *                                                allerersten Mal - das
     *                                                Klartext-Token
     */
    public function __invoke(Uuid $userId): array
    {
        $existing = $this->feeds->findForUser($userId);

        if (null !== $existing) {
            return [$existing, null];
        }

        [$feed, $token] = CalendarFeed::issueFor($userId);
        $this->feeds->save($feed);

        return [$feed, $token];
    }

    /**
     * Erzeugt das Token neu. Die alte URL hoert damit auf zu funktionieren -
     * das ist der Zweck.
     */
    public function regenerate(Uuid $userId): string
    {
        $feed = $this->feeds->findForUser($userId);

        if (null === $feed) {
            [$feed, $token] = CalendarFeed::issueFor($userId);
            $this->feeds->save($feed);

            return $token;
        }

        $token = $feed->regenerate();
        $this->feeds->save($feed);

        return $token;
    }
}
