<?php

declare(strict_types=1);

namespace Crm\Deal\UI\Component;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;
use Crm\SharedKernel\Contact\ContactFinderInterface;
use Crm\SharedKernel\Contact\ContactSummary;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Die Pipeline als Spalten je Stufe.
 *
 * Firmen und Kontakte werden je einmal fuer die ganze Seite aufgeloest, nicht
 * je Karte - beides laeuft ueber eine Modulgrenze.
 */
#[AsLiveComponent(name: 'PipelineBoard', template: '@DealModule/components/PipelineBoard.html.twig')]
final class PipelineBoard
{
    use DefaultActionTrait;

    private const LIMIT = 200;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    /**
     * @var list<Deal>|null
     */
    private ?array $cachedDeals = null;

    /**
     * @var array<string, CompanySummary>|null
     */
    private ?array $cachedCompanies = null;

    /**
     * @var array<string, ContactSummary>|null
     */
    private ?array $cachedContacts = null;

    public function __construct(
        private readonly DealRepositoryInterface $repository,
        private readonly CompanyFinderInterface $companies,
        private readonly ContactFinderInterface $contacts,
    ) {
    }

    /**
     * Die Stufen in Board-Reihenfolge, inklusive der Endzustaende.
     *
     * @return list<Stage>
     */
    public function getStages(): array
    {
        return Stage::ordered();
    }

    /**
     * @return list<Deal>
     */
    public function dealsIn(Stage $stage): array
    {
        return array_values(array_filter(
            $this->getDeals(),
            static fn (Deal $deal): bool => $deal->stage() === $stage,
        ));
    }

    /**
     * Summe einer Spalte.
     */
    public function valueIn(Stage $stage): Money
    {
        $total = Money::zero();

        foreach ($this->dealsIn($stage) as $deal) {
            $total = $total->plus($deal->value());
        }

        return $total;
    }

    /**
     * Wert aller noch offenen Chancen - die Zahl, nach der im Vertrieb
     * tatsaechlich gefragt wird.
     */
    public function getOpenValue(): Money
    {
        $total = Money::zero();

        foreach ($this->getDeals() as $deal) {
            if ($deal->isOpen()) {
                $total = $total->plus($deal->value());
            }
        }

        return $total;
    }

    /**
     * Gewonnene von allen abgeschlossenen, in Prozent.
     *
     * Null bedeutet: es ist noch nichts abgeschlossen. Das ist etwas anderes
     * als null Prozent, und das Template soll beides unterscheiden koennen.
     */
    public function getWinRate(): ?float
    {
        $won = \count($this->dealsIn(Stage::WON));
        $closed = $won + \count($this->dealsIn(Stage::LOST));

        return 0 === $closed ? null : round($won / $closed * 100, 1);
    }

    public function companyNameFor(Deal $deal): ?string
    {
        $id = $deal->companyId()?->toString();

        return null === $id ? null : ($this->getCompanies()[$id]->name ?? null);
    }

    public function contactNameFor(Deal $deal): ?string
    {
        $id = $deal->contactId()?->toString();

        return null === $id ? null : ($this->getContacts()[$id]->fullName ?? null);
    }

    public function isFiltered(): bool
    {
        return '' !== trim($this->query);
    }

    public function getTotal(): int
    {
        return $this->repository->countAll();
    }

    /**
     * @return list<Deal>
     */
    private function getDeals(): array
    {
        if (null !== $this->cachedDeals) {
            return $this->cachedDeals;
        }

        // Der Suchbegriff wird gegen Firmen- und Kontaktnamen aufgeloest,
        // bevor die eigene Tabelle gefiltert wird. Ein Join ueber die
        // Modulgrenze kommt nicht in Frage.
        $companyIds = array_map(
            static fn (CompanySummary $c): string => $c->id,
            $this->companies->searchByName($this->query),
        );
        $contactIds = array_map(
            static fn (ContactSummary $c): string => $c->id,
            $this->contacts->searchByName($this->query),
        );

        return $this->cachedDeals = $this->repository->search($this->query, $companyIds, $contactIds, self::LIMIT);
    }

    /**
     * @return array<string, CompanySummary>
     */
    private function getCompanies(): array
    {
        if (null !== $this->cachedCompanies) {
            return $this->cachedCompanies;
        }

        $ids = $this->collectIds(static fn (Deal $d): ?string => $d->companyId()?->toString());

        return $this->cachedCompanies = [] === $ids ? [] : $this->companies->findMany($ids);
    }

    /**
     * @return array<string, ContactSummary>
     */
    private function getContacts(): array
    {
        if (null !== $this->cachedContacts) {
            return $this->cachedContacts;
        }

        $ids = $this->collectIds(static fn (Deal $d): ?string => $d->contactId()?->toString());

        return $this->cachedContacts = [] === $ids ? [] : $this->contacts->findMany($ids);
    }

    /**
     * @param callable(Deal): ?string $extract
     *
     * @return list<string>
     */
    private function collectIds(callable $extract): array
    {
        return array_values(array_unique(array_filter(array_map($extract, $this->getDeals()))));
    }
}
