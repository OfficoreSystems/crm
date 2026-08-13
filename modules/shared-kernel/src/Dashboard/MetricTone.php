<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

/**
 * How a figure should be read.
 *
 * Deliberately not "red, amber, green": that would be an instruction to the
 * template and thus a design decision inside the contract. The tone says *what*
 * the value means - what it looks like is up to the interface.
 */
enum MetricTone: string
{
    /**
     * A number without judgement - "8 contacts", for instance.
     */
    case NEUTRAL = 'neutral';

    /**
     * Something is going well and may stand out.
     */
    case POSITIVE = 'positive';

    /**
     * Something demands attention - overdue tasks, for instance.
     */
    case ATTENTION = 'attention';

    public function isNotable(): bool
    {
        return self::NEUTRAL !== $this;
    }
}
