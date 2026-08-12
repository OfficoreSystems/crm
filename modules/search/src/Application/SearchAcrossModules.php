<?php

declare(strict_types=1);

namespace Crm\Search\Application;

use Crm\Search\Domain\SearchHit;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;

/**
 * Sucht ueber alle Module, die sich als Subjekt anbieten.
 *
 * Dieses Modul besitzt keine eigenen Daten und kennt kein anderes Modul. Es
 * fragt die SubjectResolverRegistry, sortiert das Ergebnis und liefert es
 * zurueck - mehr passiert hier nicht.
 */
final readonly class SearchAcrossModules
{
    /**
     * Obergrenze je Modul. Ohne sie fuellt ein Modul mit vielen Treffern die
     * ganze Liste, und die eine Firma, die man eigentlich suchte, steht auf
     * Platz 40.
     */
    private const PER_TYPE = 8;

    public function __construct(
        private SubjectResolverRegistry $subjects,
    ) {
    }

    /**
     * @return list<SearchHit> Absteigend nach Relevanz, bei Gleichstand
     *                         alphabetisch.
     */
    public function __invoke(string $query, ?string $type = null, int $limit = 20): array
    {
        if ('' === trim($query)) {
            return [];
        }

        $hits = array_map(
            static fn (ResolvedSubject $subject): SearchHit => SearchHit::for($subject, $query),
            $this->subjects->searchAll($query, $type, self::PER_TYPE),
        );

        usort(
            $hits,
            static fn (SearchHit $a, SearchHit $b): int => [$b->score, $a->subject->label] <=> [$a->score, $b->subject->label],
        );

        return \array_slice($hits, 0, max(1, $limit));
    }
}
