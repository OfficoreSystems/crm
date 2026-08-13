<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Application;

use Crm\Calendar\Application\ScheduleAppointment;
use Crm\Calendar\Application\ScheduleAppointmentCommand;
use Crm\Calendar\Domain\TimeSpan;
use Crm\Calendar\Domain\UnresolvableSubject;
use Crm\Calendar\Tests\Double\InMemoryAppointmentRepository;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScheduleAppointmentTest extends TestCase
{
    #[Test]
    public function an_appointment_without_a_subject_needs_no_resolver(): void
    {
        // Ein Teammeeting gehoert zu keinem Datensatz - und muss auch ohne
        // jedes andere Modul eintragbar sein.
        [$schedule, $appointments] = $this->schedule(new SubjectResolverRegistry([]));

        ($schedule)($this->command());

        self::assertSame(1, $appointments->countAll());
    }

    #[Test]
    public function a_subject_nobody_resolves_is_refused(): void
    {
        // Geprueft wird der Typ, nicht die ID: ein Termin an einem Typ, den
        // niemand aufloest, bliebe in der Uebersicht dauerhaft namenlos.
        [$schedule, $appointments] = $this->schedule();

        $this->expectException(UnresolvableSubject::class);

        try {
            ($schedule)($this->command(new SubjectRef('rechnung', 'r-1')));
        } finally {
            self::assertSame(0, $appointments->countAll());
        }
    }

    #[Test]
    public function a_known_subject_is_accepted(): void
    {
        [$schedule, $appointments] = $this->schedule();

        $appointment = ($schedule)($this->command(new SubjectRef('contact', 'kontakt-1')));

        self::assertSame('contact', $appointment->subject()?->type);
        self::assertSame(1, $appointments->countAll());
    }

    /**
     * @return array{0: ScheduleAppointment, 1: InMemoryAppointmentRepository}
     */
    private function schedule(?SubjectResolverRegistry $registry = null): array
    {
        $appointments = new InMemoryAppointmentRepository();

        return [
            new ScheduleAppointment(
                $appointments,
                $registry ?? new SubjectResolverRegistry([new FakeContactResolver()]),
            ),
            $appointments,
        ];
    }

    private function command(?SubjectRef $subject = null): ScheduleAppointmentCommand
    {
        return new ScheduleAppointmentCommand(
            title: 'Vor-Ort-Termin',
            when: TimeSpan::of(
                new \DateTimeImmutable('2026-08-20 10:00:00', new \DateTimeZone('UTC')),
                new \DateTimeImmutable('2026-08-20 11:00:00', new \DateTimeZone('UTC')),
            ),
            subject: $subject,
        );
    }
}

final class FakeContactResolver implements SubjectResolverInterface
{
    public function type(): string
    {
        return 'contact';
    }

    public function typeLabel(): string
    {
        return 'Kontakt';
    }

    public function resolve(array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            $found[$id] = new ResolvedSubject('contact', $id, 'Anna Andresen');
        }

        return $found;
    }

    public function search(string $query, int $limit = 10): array
    {
        return [];
    }
}
