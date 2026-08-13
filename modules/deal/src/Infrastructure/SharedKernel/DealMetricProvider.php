<?php

declare(strict_types=1);

namespace Crm\Deal\Infrastructure\SharedKernel;

use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;
use Crm\SharedKernel\Dashboard\MetricTone;

/**
 * Die Kennzahlen, nach denen im Vertrieb tatsaechlich gefragt wird.
 *
 * Aggregiert wird in einer einzigen Abfrage - statsByStage() liefert Anzahl
 * und Summe je Stufe aus der Datenbank. Das Dashboard bekommt fertige Werte
 * und rechnet nichts nach.
 */
final readonly class DealMetricProvider implements MetricProviderInterface
{
    public function __construct(
        private DealRepositoryInterface $deals,
    ) {
    }

    public function getMetrics(): iterable
    {
        $stats = $this->deals->statsByStage();

        yield new Metric(
            key: 'deal.pipeline_value',
            label: 'deal.metric.pipeline',
            value: $this->openValue($stats)->asDecimal(),
            currency: $this->openValue($stats)->currency,
            description: 'deal.metric.pipeline_description',
            parameters: ['%count%' => $this->openCount($stats)],
            route: 'deal_index',
            priority: 100,
        );

        $winRate = $this->winRate($stats);

        yield new Metric(
            key: 'deal.win_rate',
            label: 'deal.metric.win_rate',
            value: null === $winRate ? '—' : $winRate.' %',
            description: null === $winRate
                ? 'deal.metric.win_rate_unknown'
                : 'deal.metric.win_rate_description',
            parameters: [
                '%won%' => $this->countIn($stats, Stage::WON),
                '%lost%' => $this->countIn($stats, Stage::LOST),
            ],
            route: 'deal_index',
            priority: 90,
            // Erst ab einer Aussage ueberhaupt bewerten - bei zwei
            // abgeschlossenen Chancen sagt eine Quote nichts.
            tone: null !== $winRate && $winRate >= 50.0 ? MetricTone::POSITIVE : MetricTone::NEUTRAL,
        );
    }

    /**
     * @param array<string, array{count: int, cents: int}> $stats
     */
    private function openValue(array $stats): Money
    {
        $total = Money::zero();

        foreach (Stage::openStages() as $stage) {
            $total = $total->plus(Money::fromCents($stats[$stage->value]['cents'] ?? 0));
        }

        return $total;
    }

    /**
     * @param array<string, array{count: int, cents: int}> $stats
     */
    private function openCount(array $stats): int
    {
        $count = 0;

        foreach (Stage::openStages() as $stage) {
            $count += $this->countIn($stats, $stage);
        }

        return $count;
    }

    /**
     * @param array<string, array{count: int, cents: int}> $stats
     */
    private function winRate(array $stats): ?float
    {
        $won = $this->countIn($stats, Stage::WON);
        $closed = $won + $this->countIn($stats, Stage::LOST);

        return 0 === $closed ? null : round($won / $closed * 100, 1);
    }

    /**
     * @param array<string, array{count: int, cents: int}> $stats
     */
    private function countIn(array $stats, Stage $stage): int
    {
        return $stats[$stage->value]['count'] ?? 0;
    }
}
