<?php

declare(strict_types=1);

namespace Crm\Activity\Infrastructure\SharedKernel;

use Crm\Activity\Domain\Activity;
use Crm\SharedKernel\Security\RecordOwnership;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
use Crm\SharedKernel\Security\RestrictedColumns;

final readonly class ActivityOwnership implements RecordOwnershipInterface
{
    public function module(): string
    {
        return 'activity';
    }

    public function supports(object $record): bool
    {
        return $record instanceof Activity;
    }

    public function ownershipOf(object $record): RecordOwnership
    {
        \assert($record instanceof Activity);

        return new RecordOwnership(
            ownerId: $record->authorId()?->toString(),
            teamId: $record->authorTeamId()?->toString(),
        );
    }

    public function restrictedColumns(): RestrictedColumns
    {
        // Hier heissen die Spalten author_*, nicht owner_* - genau deshalb
        // stehen sie beim Modul und nicht als Vorgabe im Shared Kernel.
        return new RestrictedColumns(
            entityClass: Activity::class,
            ownerColumn: 'author_id',
            teamColumn: 'author_team_id',
        );
    }
}
