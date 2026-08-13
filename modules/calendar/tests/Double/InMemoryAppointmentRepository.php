<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Double;

use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

final class InMemoryAppointmentRepository implements AppointmentRepositoryInterface
{
    /**
     * @var array<string, Appointment>
     */
    private array $appointments = [];

    public function save(Appointment $appointment): void
    {
        $this->appointments[$appointment->id()->toString()] = $appointment;
    }

    public function remove(Appointment $appointment): void
    {
        unset($this->appointments[$appointment->id()->toString()]);
    }

    public function find(Uuid $id): ?Appointment
    {
        return $this->appointments[$id->toString()] ?? null;
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $until, int $limit = 500): array
    {
        return $this->take(
            array_filter(
                $this->chronological(),
                static fn (Appointment $a): bool => $a->startsAt() < $until && $a->endsAt() > $from,
            ),
            $limit,
        );
    }

    public function findUpcoming(\DateTimeImmutable $from, int $limit = 50): array
    {
        return $this->take(
            array_filter($this->chronological(), static fn (Appointment $a): bool => $a->endsAt() > $from),
            $limit,
        );
    }

    public function findForSubject(SubjectRef $subject, int $limit = 50): array
    {
        return $this->take(
            array_filter(
                $this->chronological(),
                static fn (Appointment $a): bool => $a->subject()?->key() === $subject->key(),
            ),
            $limit,
        );
    }

    public function findForOwnerBetween(
        Uuid $ownerId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
        int $limit = 1000,
    ): array {
        return $this->take(
            array_filter(
                $this->chronological(),
                static fn (Appointment $a): bool => true === $a->ownerId()?->equals($ownerId)
                    && $a->startsAt() < $until
                    && $a->endsAt() > $from,
            ),
            $limit,
        );
    }

    public function countUpcoming(\DateTimeImmutable $from): int
    {
        return \count($this->findUpcoming($from, \PHP_INT_MAX));
    }

    public function countAll(): int
    {
        return \count($this->appointments);
    }

    /**
     * @return list<Appointment>
     */
    private function chronological(): array
    {
        $sorted = array_values($this->appointments);

        usort($sorted, static fn (Appointment $a, Appointment $b): int => $a->startsAt() <=> $b->startsAt());

        return $sorted;
    }

    /**
     * @param array<int, Appointment> $appointments
     *
     * @return list<Appointment>
     */
    private function take(array $appointments, int $limit): array
    {
        return \array_slice(array_values($appointments), 0, max(1, $limit));
    }
}
