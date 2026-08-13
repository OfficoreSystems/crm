<?php

declare(strict_types=1);

namespace Crm\Document\Infrastructure\SharedKernel;

use Crm\Document\Domain\FileSize;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\SharedKernel\Dashboard\Metric;
use Crm\SharedKernel\Dashboard\MetricProviderInterface;

final readonly class DocumentMetricProvider implements MetricProviderInterface
{
    public function __construct(
        private DocumentRepositoryInterface $documents,
    ) {
    }

    public function getMetrics(): iterable
    {
        $count = $this->documents->countAll();

        yield new Metric(
            key: 'document.count',
            label: 'Dokumente',
            value: (string) $count,
            // Der belegte Platz interessiert, weil Objektspeicher nach Volumen
            // abgerechnet wird - anders als Tabellenzeilen.
            description: 0 === $count
                ? 'noch nichts abgelegt'
                : FileSize::humanize($this->documents->totalBytes()).' belegt',
            route: 'document_index',
            priority: 40,
        );
    }
}
