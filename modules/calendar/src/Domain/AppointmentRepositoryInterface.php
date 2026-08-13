<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

interface AppointmentRepositoryInterface
{
    public function save(Appointment $appointment): void;

    public function remove(Appointment $appointment): void;

    public function find(Uuid $id): ?Appointment;

    /**
     * Termine, die im Zeitfenster liegen oder hineinragen.
     *
     * @return list<Appointment>
     */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $until, int $limit = 500): array;

    /**
     * @return list<Appointment>
     */
    public function findUpcoming(\DateTimeImmutable $from, int $limit = 50): array;

    /**
     * @return list<Appointment>
     */
    public function findForSubject(SubjectRef $subject, int $limit = 50): array;

    /**
     * Die Termine *eines* Besitzers, ausdruecklich nach Besitzer gefiltert.
     *
     * Fuer den ICS-Feed. Dort gibt es keinen angemeldeten Benutzer, also ist
     * der Doctrine-Sichtbarkeitsfilter abgeschaltet - er haette niemanden, an
     * dem er sich orientieren koennte. Die Einschraenkung muss deshalb hier in
     * der Abfrage stehen und darf nirgendwo sonst herkommen.
     *
     * @return list<Appointment>
     */
    public function findForOwnerBetween(
        Uuid $ownerId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
        int $limit = 1000,
    ): array;

    public function countUpcoming(\DateTimeImmutable $from): int;

    public function countAll(): int;
}
