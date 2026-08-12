<?php

declare(strict_types=1);

namespace Crm\Search\Domain;

use Crm\SharedKernel\Subject\ResolvedSubject;

/**
 * Ein bewerteter Treffer.
 */
final readonly class SearchHit
{
    public function __construct(
        public ResolvedSubject $subject,
        public int $score,
    ) {
    }

    public static function for(ResolvedSubject $subject, string $query): self
    {
        return new self($subject, Relevance::score($subject, $query));
    }

    public function isStrong(): bool
    {
        return $this->score >= Relevance::PREFIX;
    }
}
