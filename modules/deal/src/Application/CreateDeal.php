<?php

declare(strict_types=1);

namespace Crm\Deal\Application;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\DealRepositoryInterface;

/**
 * Use-Case: eine Verkaufschance anlegen.
 *
 * Prueft die Verweise auf Firma, Kontakt und Besitzer nicht - wie bei
 * contact wuerde eine Pflichtpruefung jede Zuordnung unmoeglich machen,
 * sobald das jeweilige Modul fehlt.
 */
final readonly class CreateDeal
{
    public function __construct(
        private DealRepositoryInterface $deals,
    ) {
    }

    public function __invoke(CreateDealCommand $command): Deal
    {
        $deal = Deal::create(
            title: $command->title,
            value: $command->value,
            stage: $command->stage,
            companyId: $command->companyId,
            contactId: $command->contactId,
            ownerId: $command->ownerId,
            ownerTeamId: $command->ownerTeamId,
            expectedCloseDate: $command->expectedCloseDate,
        );

        $this->deals->save($deal);

        return $deal;
    }
}
