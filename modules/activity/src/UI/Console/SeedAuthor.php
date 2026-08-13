<?php

declare(strict_types=1);

namespace Crm\Activity\UI\Console;

use Symfony\Component\Uid\Uuid;

/**
 * Autor fuer die Beispieldaten, aus dem UserFinder aufgeloest.
 */
final readonly class SeedAuthor
{
    public function __construct(
        public Uuid $id,
        public Uuid $teamId,
        public string $name,
    ) {
    }
}
