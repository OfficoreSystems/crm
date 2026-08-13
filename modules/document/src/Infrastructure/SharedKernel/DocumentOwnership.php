<?php

declare(strict_types=1);

namespace Crm\Document\Infrastructure\SharedKernel;

use Crm\Document\Domain\Document;
use Crm\SharedKernel\Security\RecordOwnership;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
use Crm\SharedKernel\Security\RestrictedColumns;

/**
 * Sagt Voter und Sichtbarkeitsfilter, wem ein Dokument gehoert.
 *
 * Ohne diese Klasse waeren Dokumente fuer jeden sichtbar, der die Seite
 * aufrufen darf - und eine hochgeladene Datei ist oft vertraulicher als der
 * Datensatz, an dem sie haengt.
 */
final readonly class DocumentOwnership implements RecordOwnershipInterface
{
    public function module(): string
    {
        return 'document';
    }

    public function supports(object $record): bool
    {
        return $record instanceof Document;
    }

    public function ownershipOf(object $record): RecordOwnership
    {
        \assert($record instanceof Document);

        return new RecordOwnership(
            ownerId: $record->ownerId()?->toString(),
            teamId: $record->ownerTeamId()?->toString(),
        );
    }

    public function restrictedColumns(): RestrictedColumns
    {
        return new RestrictedColumns(
            entityClass: Document::class,
            ownerColumn: 'owner_id',
            teamColumn: 'owner_team_id',
        );
    }
}
