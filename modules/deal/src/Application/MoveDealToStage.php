<?php

declare(strict_types=1);

namespace Crm\Deal\Application;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Domain\Stage;

/**
 * Use-Case: eine Chance in eine andere Stufe schieben.
 *
 * Eigener Use-Case und nicht nur ein Setter, weil hier spaeter das Event
 * haengen wird, ueber das activity und reporting mitbekommen, dass sich etwas
 * bewegt hat.
 */
final readonly class MoveDealToStage
{
    public function __construct(
        private DealRepositoryInterface $deals,
    ) {
    }

    public function __invoke(Deal $deal, Stage $stage, ?\DateTimeImmutable $at = null): Deal
    {
        $deal->moveTo($stage, $at);
        $this->deals->save($deal);

        return $deal;
    }
}
