<?php

declare(strict_types=1);

namespace Crm\Search\Tests\Double;

use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverInterface;

/**
 * Steht im Test fuer ein beliebiges Modul, das sich als Subjekt anbietet.
 *
 * Sucht absichtlich grosszuegig - so wie ein echtes Modul, das auch in
 * Feldern sucht, die in der Trefferliste gar nicht auftauchen.
 */
final class StubResolver implements SubjectResolverInterface
{
    /**
     * @param list<ResolvedSubject> $subjects
     */
    public function __construct(
        private readonly string $type,
        private readonly string $typeLabel,
        private readonly array $subjects,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function typeLabel(): string
    {
        return $this->typeLabel;
    }

    public function resolve(array $ids): array
    {
        $resolved = [];

        foreach ($this->subjects as $subject) {
            if (\in_array($subject->id, $ids, true)) {
                $resolved[$subject->id] = $subject;
            }
        }

        return $resolved;
    }

    public function search(string $query, int $limit = 10): array
    {
        $needle = mb_strtolower(trim($query));

        $found = array_values(array_filter(
            $this->subjects,
            static function (ResolvedSubject $s) use ($needle): bool {
                if ('' === $needle) {
                    return true;
                }

                return str_contains(mb_strtolower($s->label.' '.($s->description ?? '')), $needle);
            },
        ));

        return \array_slice($found, 0, max(1, $limit));
    }
}
