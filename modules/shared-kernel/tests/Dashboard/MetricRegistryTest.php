<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Dashboard;

use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;
use Crm\SharedKernel\Dashboard\MetricRegistry;
use Crm\SharedKernel\Dashboard\MetricTone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetricRegistryTest extends TestCase
{
    #[Test]
    public function it_collects_from_every_provider(): void
    {
        $registry = new MetricRegistry([
            $this->provider($this->metric('deal.pipeline_value', 'Pipeline')),
            $this->provider($this->metric('contact.total', 'Kontakte')),
        ]);

        self::assertCount(2, $registry->all());
        self::assertFalse($registry->isEmpty());
    }

    #[Test]
    public function it_sorts_by_priority_descending(): void
    {
        $registry = new MetricRegistry([
            $this->provider(
                $this->metric('contact.total', 'Kontakte', priority: 10),
                $this->metric('deal.pipeline_value', 'Pipeline', priority: 100),
                $this->metric('company.total', 'Firmen', priority: 50),
            ),
        ]);

        self::assertSame(
            ['Pipeline', 'Firmen', 'Kontakte'],
            array_map(static fn (Metric $m): string => $m->label, $registry->all()),
        );
    }

    #[Test]
    public function ties_are_broken_alphabetically(): void
    {
        // Sonst haengt die Reihenfolge davon ab, in welcher Reihenfolge die
        // Module registriert sind - und die aendert sich beim Installieren.
        $registry = new MetricRegistry([
            $this->provider(
                $this->metric('deal.zeta', 'Zeta', priority: 10),
                $this->metric('deal.alpha', 'Alpha', priority: 10),
            ),
        ]);

        self::assertSame(
            ['Alpha', 'Zeta'],
            array_map(static fn (Metric $m): string => $m->label, $registry->all()),
        );
    }

    #[Test]
    public function two_providers_claiming_the_same_key_are_refused(): void
    {
        // Ein Installationsfehler, der sonst nur als gelegentlich falsche
        // Zahl auffiele.
        $registry = new MetricRegistry([
            $this->provider($this->metric('deal.total', 'Chancen')),
            $this->provider($this->metric('deal.total', 'Auch Chancen')),
        ]);

        $this->expectException(\LogicException::class);

        $registry->all();
    }

    #[Test]
    public function it_reports_the_ones_that_want_attention(): void
    {
        $registry = new MetricRegistry([
            $this->provider(
                $this->metric('activity.overdue', 'Überfällig', tone: MetricTone::ATTENTION),
                $this->metric('deal.win_rate', 'Quote', tone: MetricTone::POSITIVE),
                $this->metric('contact.total', 'Kontakte'),
            ),
        ]);

        self::assertCount(2, $registry->notable());
        self::assertCount(3, $registry->all());
    }

    #[Test]
    public function it_can_filter_by_module(): void
    {
        $registry = new MetricRegistry([
            $this->provider(
                $this->metric('deal.pipeline_value', 'Pipeline'),
                $this->metric('deal.win_rate', 'Quote'),
                $this->metric('contact.total', 'Kontakte'),
            ),
        ]);

        self::assertCount(2, $registry->forModule('deal'));
        self::assertCount(1, $registry->forModule('contact'));
        self::assertSame([], $registry->forModule('invoice'));
    }

    #[Test]
    public function without_any_provider_it_stays_empty_instead_of_failing(): void
    {
        $registry = new MetricRegistry([]);

        self::assertTrue($registry->isEmpty());
        self::assertSame([], $registry->all());
        self::assertSame([], $registry->notable());
    }

    #[Test]
    public function a_metric_knows_its_module_and_whether_it_links(): void
    {
        $linked = $this->metric('deal.pipeline_value', 'Pipeline', route: 'deal_index');

        self::assertSame('deal', $linked->module());
        self::assertTrue($linked->isLinkable());
        self::assertFalse($this->metric('deal.pipeline_value', 'Pipeline')->isLinkable());
    }

    #[Test]
    #[DataProvider('invalidKeys')]
    public function it_rejects_keys_without_a_module_prefix(string $key): void
    {
        // Ohne Praefix heisst die Kennzahl in drei Modulen "total", und wer
        // zuletzt registriert wird, gewinnt - lautlos.
        $this->expectException(\InvalidArgumentException::class);

        $this->metric($key, 'Irgendwas');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'ohne Punkt' => ['total'];
        yield 'leer' => [''];
        yield 'Grossbuchstaben' => ['Deal.Total'];
        yield 'zwei Punkte' => ['deal.pipeline.value'];
        yield 'nur Praefix' => ['deal.'];
        yield 'nur Suffix' => ['.total'];
    }

    #[Test]
    public function it_rejects_a_blank_label(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->metric('deal.total', '   ');
    }

    #[Test]
    public function only_the_neutral_tone_is_unremarkable(): void
    {
        self::assertFalse(MetricTone::NEUTRAL->isNotable());
        self::assertTrue(MetricTone::POSITIVE->isNotable());
        self::assertTrue(MetricTone::ATTENTION->isNotable());
    }

    private function metric(
        string $key,
        string $label,
        int $priority = 0,
        ?string $route = null,
        MetricTone $tone = MetricTone::NEUTRAL,
    ): Metric {
        return new Metric($key, $label, '1', route: $route, priority: $priority, tone: $tone);
    }

    private function provider(Metric ...$metrics): MetricProviderInterface
    {
        return new class($metrics) implements MetricProviderInterface {
            /**
             * @param list<Metric> $metrics
             */
            public function __construct(private readonly array $metrics)
            {
            }

            public function getMetrics(): iterable
            {
                yield from $this->metrics;
            }
        };
    }
}
