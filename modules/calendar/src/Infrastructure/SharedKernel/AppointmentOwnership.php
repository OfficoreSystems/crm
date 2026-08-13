<?php

declare(strict_types=1);

namespace Crm\Calendar\Infrastructure\SharedKernel;

use Crm\Calendar\Domain\Appointment;
use Crm\SharedKernel\Security\RecordOwnership;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
use Crm\SharedKernel\Security\RestrictedColumns;

final readonly class AppointmentOwnership implements RecordOwnershipInterface
{
    public function module(): string
    {
        return 'calendar';
    }

    public function supports(object $record): bool
    {
        return $record instanceof Appointment;
    }

    public function ownershipOf(object $record): RecordOwnership
    {
        \assert($record instanceof Appointment);

        return new RecordOwnership(
            ownerId: $record->ownerId()?->toString(),
            teamId: $record->ownerTeamId()?->toString(),
        );
    }

    public function restrictedColumns(): RestrictedColumns
    {
        return new RestrictedColumns(
            entityClass: Appointment::class,
            ownerColumn: 'owner_id',
            teamColumn: 'owner_team_id',
        );
    }
}
