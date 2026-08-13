<?php

declare(strict_types=1);

namespace Crm\Deal\UI\Console;

use Symfony\Component\Uid\Uuid;

/**
 * Besitzer fuer die Beispieldaten, aus dem UserFinder aufgeloest.
 */
final readonly class SeedOwner
{
    public function __construct(
        public ?Uuid $id,
        public ?Uuid $teamId,
        public string $name,
    ) {
    }
}
