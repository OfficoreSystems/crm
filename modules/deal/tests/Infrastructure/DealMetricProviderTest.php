<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Infrastructure;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\Deal\Infrastructure\SharedKernel\DealMetricProvider;
use Crm\Deal\Tests\Double\InMemoryDealRepository;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricTone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DealMetricProviderTest extends TestCase
{
    #[Test]
    public function the_pipeline_value_counts_only_open_deals(): void
    {
        $metrics = $this->metricsFor([
            Deal::create('offen', Money::fromDecimal('1000.00'), Stage::NEGOTIATION),
            Deal::create('gewonnen', Money::fromDecimal('5000.00'), Stage::WON),
            Deal::create('verloren', Money::fromDecimal('9000.00'), Stage::LOST),
        ]);

        self::assertSame('1000.00 EUR', $metrics['deal.pipeline_value']->value);
        self::assertSame('1 offene Chancen', $metrics['deal.pipeline_value']->description);
    }

    #[Test]
    public function the_win_rate_counts_only_closed_deals(): void
    {
        $metrics = $this->metricsFor([
            Deal::create('offen', stage: Stage::LEAD),
            Deal::create('gewonnen', stage: Stage::WON),
            Deal::create('verloren', stage: Stage::LOST),
        ]);

        self::assertSame('50 %', $metrics['deal.win_rate']->value);
    }

    #[Test]
    public function a_good_win_rate_is_marked_as_positive(): void
    {
        $good = $this->metricsFor([
            Deal::create('a', stage: Stage::WON),
            Deal::create('b', stage: Stage::WON),
            Deal::create('c', stage: Stage::LOST),
        ]);
        $poor = $this->metricsFor([
            Deal::create('a', stage: Stage::WON),
            Deal::create('b', stage: Stage::LOST),
            Deal::create('c', stage: Stage::LOST),
        ]);

        self::assertSame(MetricTone::POSITIVE, $good['deal.win_rate']->tone);
        self::assertSame(MetricTone::NEUTRAL, $poor['deal.win_rate']->tone);
    }

    #[Test]
    public function without_closed_deals_the_rate_is_a_dash_and_stays_neutral(): void
    {
        // Null Prozent waere eine Aussage - "noch nichts abgeschlossen" ist
        // etwas anderes.
        $metrics = $this->metricsFor([Deal::create('offen', stage: Stage::LEAD)]);

        self::assertSame('—', $metrics['deal.win_rate']->value);
        self::assertSame('noch nichts abgeschlossen', $metrics['deal.win_rate']->description);
        self::assertSame(MetricTone::NEUTRAL, $metrics['deal.win_rate']->tone);
    }

    #[Test]
    public function an_empty_pipeline_yields_zero_not_an_error(): void
    {
        $metrics = $this->metricsFor([]);

        self::assertSame('0.00 EUR', $metrics['deal.pipeline_value']->value);
        self::assertSame('—', $metrics['deal.win_rate']->value);
    }

    #[Test]
    public function every_metric_links_back_to_the_pipeline(): void
    {
        foreach ($this->metricsFor([]) as $metric) {
            self::assertTrue($metric->isLinkable());
            self::assertSame('deal', $metric->module());
        }
    }

    /**
     * @param list<Deal> $deals
     *
     * @return array<string, Metric>
     */
    private function metricsFor(array $deals): array
    {
        $repository = new InMemoryDealRepository();

        foreach ($deals as $deal) {
            $repository->save($deal);
        }

        $metrics = [];

        foreach ((new DealMetricProvider($repository))->getMetrics() as $metric) {
            $metrics[$metric->key] = $metric;
        }

        return $metrics;
    }
}
