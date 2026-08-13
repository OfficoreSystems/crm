<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

/**
 * Collects the figures of every module.
 *
 * Like the MenuRegistry: the core asks rather than knowing who delivers.
 */
final class MetricRegistry
{
    /**
     * @var list<Metric>|null
     */
    private ?array $cached = null;

    /**
     * @param iterable<MetricProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return list<Metric> Descending by priority, alphabetically by label on a
     *                      tie.
     */
    public function all(): array
    {
        if (null !== $this->cached) {
            return $this->cached;
        }

        $metrics = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getMetrics() as $metric) {
                if (isset($metrics[$metric->key])) {
                    // Two modules claiming the same key are an installation
                    // error - and one that would otherwise only show up as an
                    // occasionally wrong number.
                    throw new \LogicException(sprintf(
                        'Der Metric-Schluessel "%s" wird von zwei Anbietern belegt.',
                        $metric->key,
                    ));
                }

                $metrics[$metric->key] = $metric;
            }
        }

        $sorted = array_values($metrics);

        usort(
            $sorted,
            static fn (Metric $a, Metric $b): int => [$b->priority, $a->label] <=> [$a->priority, $b->label],
        );

        return $this->cached = $sorted;
    }

    /**
     * Only the figures of one module.
     *
     * @return list<Metric>
     */
    public function forModule(string $module): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Metric $metric): bool => $metric->module() === $module,
        ));
    }

    /**
     * Figures that demand attention - for a highlighted row at the top.
     *
     * @return list<Metric>
     */
    public function notable(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Metric $metric): bool => $metric->tone->isNotable(),
        ));
    }

    public function isEmpty(): bool
    {
        return [] === $this->all();
    }
}
