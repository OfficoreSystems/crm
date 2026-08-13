<?php

declare(strict_types=1);

namespace Crm\Calendar\Application;

use Crm\Calendar\Domain\TimeSpan;
use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

final readonly class ScheduleAppointmentCommand
{
    public function __construct(
        public string $title,
        public TimeSpan $when,
        public ?string $description = null,
        public ?string $location = null,
        public ?SubjectRef $subject = null,
        public ?Uuid $ownerId = null,
        public ?Uuid $ownerTeamId = null,
    ) {
    }
}
