<?php

declare(strict_types=1);

namespace Crm\Deal\Infrastructure\SharedKernel;

use Crm\Deal\Domain\Deal;
use Crm\SharedKernel\Security\RecordOwnership;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
use Crm\SharedKernel\Security\RestrictedColumns;

/**
 * Sagt dem Voter, wem eine Verkaufschance gehoert.
 *
 * Der Voter kennt dieses Modul nicht - er bekommt nur Modulname, Besitzer und
 * Team und vergleicht das mit der Rechtematrix. Der Sichtbarkeitsfilter
 * bekommt zusaetzlich die Spaltennamen und schraenkt damit bereits in SQL ein.
 */
final readonly class DealOwnership implements RecordOwnershipInterface
{
    public function module(): string
    {
        return 'deal';
    }

    public function supports(object $record): bool
    {
        return $record instanceof Deal;
    }

    public function ownershipOf(object $record): RecordOwnership
    {
        \assert($record instanceof Deal);

        return new RecordOwnership(
            ownerId: $record->ownerId()?->toString(),
            teamId: $record->ownerTeamId()?->toString(),
        );
    }

    public function restrictedColumns(): RestrictedColumns
    {
        return new RestrictedColumns(
            entityClass: Deal::class,
            ownerColumn: 'owner_id',
            teamColumn: 'owner_team_id',
        );
    }
}
