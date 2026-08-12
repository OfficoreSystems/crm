<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

/**
 * Sammelt die Kennzahlen aller Module ein.
 *
 * Wie die MenuRegistry: der Core fragt ab, statt zu wissen, wer liefert.
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
     * @return list<Metric> Absteigend nach Priority, bei Gleichstand
     *                      alphabetisch nach Label.
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
                    // Zwei Module, die denselben Schluessel beanspruchen,
                    // sind ein Installationsfehler - und einer, der sonst nur
                    // als gelegentlich falsche Zahl auffiele.
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
     * Nur die Kennzahlen eines Moduls.
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
     * Kennzahlen, die Aufmerksamkeit verlangen - fuer eine hervorgehobene
     * Zeile ganz oben.
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
