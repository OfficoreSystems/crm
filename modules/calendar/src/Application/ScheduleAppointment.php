<?php

declare(strict_types=1);

namespace Crm\Calendar\Application;

use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\Calendar\Domain\UnresolvableSubject;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;

/**
 * Use-Case: einen Termin anlegen.
 *
 * Der Bezug ist optional - ein Teammeeting gehoert zu keinem Datensatz. Ist
 * einer angegeben, wird der *Typ* geprueft: ein Termin an einem Typ, den
 * niemand aufloest, waere in der Uebersicht dauerhaft namenlos, und der
 * Tippfehler faellt sonst erst Wochen spaeter auf.
 */
final readonly class ScheduleAppointment
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private SubjectResolverRegistry $subjects,
    ) {
    }

    public function __invoke(ScheduleAppointmentCommand $command): Appointment
    {
        if (null !== $command->subject && !$this->subjects->supports($command->subject->type)) {
            throw UnresolvableSubject::ofType(
                $command->subject->type,
                array_keys($this->subjects->supportedTypes()),
            );
        }

        $appointment = Appointment::schedule(
            title: $command->title,
            when: $command->when,
            description: $command->description,
            location: $command->location,
            subject: $command->subject,
            ownerId: $command->ownerId,
            ownerTeamId: $command->ownerTeamId,
        );

        $this->appointments->save($appointment);

        return $appointment;
    }
}
