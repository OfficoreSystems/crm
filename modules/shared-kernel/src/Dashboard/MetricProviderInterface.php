<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

/**
 * Extension point: a module delivers figures for the home page.
 *
 * As with the menu, a module registers purely by implementing this - the
 * dashboard has no list one would have to be entered into.
 *
 * The figures arrive **pre-aggregated**. The dashboard computes nothing and
 * queries no foreign tables; every module counts for itself, in its own
 * database, using the queries it has anyway.
 */
interface MetricProviderInterface
{
    /**
     * @return iterable<Metric>
     */
    public function getMetrics(): iterable;
}
