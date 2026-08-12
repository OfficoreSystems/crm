<?php

declare(strict_types=1);

namespace Crm\Search\UI\Component;

use Crm\Search\Application\SearchAcrossModules;
use Crm\Search\Domain\SearchHit;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'GlobalSearch', template: '@SearchModule/components/GlobalSearch.html.twig')]
final class GlobalSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $type = '';

    /**
     * @var list<SearchHit>|null
     */
    private ?array $cachedHits = null;

    public function __construct(
        private readonly SearchAcrossModules $search,
        private readonly SubjectResolverRegistry $subjects,
    ) {
    }

    /**
     * @return list<SearchHit>
     */
    public function getHits(): array
    {
        return $this->cachedHits ??= ($this->search)(
            $this->query,
            '' === $this->type ? null : $this->type,
        );
    }

    /**
     * Treffer nach Typ gruppiert, in der Reihenfolge des besten Treffers.
     *
     * @return array<string, list<SearchHit>>
     */
    public function getGroupedHits(): array
    {
        $grouped = [];

        foreach ($this->getHits() as $hit) {
            $grouped[$hit->subject->type][] = $hit;
        }

        return $grouped;
    }

    /**
     * Die durchsuchbaren Typen - waechst und schrumpft mit den Modulen.
     *
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        return $this->subjects->supportedTypes();
    }

    public function labelForType(string $type): string
    {
        return $this->getTypes()[$type] ?? $type;
    }

    public function hasQuery(): bool
    {
        return '' !== trim($this->query);
    }

    public function getStrongHits(): int
    {
        return \count(array_filter($this->getHits(), static fn (SearchHit $h): bool => $h->isStrong()));
    }
}
