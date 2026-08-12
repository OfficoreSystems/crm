<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Double;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Domain\Stage;
use Symfony\Component\Uid\Uuid;

final class InMemoryDealRepository implements DealRepositoryInterface
{
    /**
     * @var array<string, Deal>
     */
    private array $deals = [];

    public function save(Deal $deal): void
    {
        $this->deals[(string) $deal->id()] = $deal;
    }

    public function remove(Deal $deal): void
    {
        unset($this->deals[(string) $deal->id()]);
    }

    public function find(Uuid $id): ?Deal
    {
        return $this->deals[(string) $id] ?? null;
    }

    public function search(string $query, array $companyIds = [], array $contactIds = [], int $limit = 100): array
    {
        $needle = mb_strtolower(trim($query));

        $matches = array_values(array_filter(
            $this->deals,
            static function (Deal $deal) use ($needle, $companyIds, $contactIds): bool {
                if ('' === $needle) {
                    return true;
                }

                if (str_contains(mb_strtolower($deal->title()), $needle)) {
                    return true;
                }

                if (\in_array($deal->companyId()?->toString(), $companyIds, true)) {
                    return true;
                }

                return \in_array($deal->contactId()?->toString(), $contactIds, true);
            },
        ));

        return \array_slice(self::sorted($matches), 0, max(1, $limit));
    }

    public function findByStage(Stage $stage, int $limit = 100): array
    {
        $matches = array_values(array_filter(
            $this->deals,
            static fn (Deal $deal): bool => $deal->stage() === $stage,
        ));

        return \array_slice(self::sorted($matches), 0, max(1, $limit));
    }

    public function statsByStage(): array
    {
        $stats = [];

        foreach ($this->deals as $deal) {
            $key = $deal->stage()->value;
            $stats[$key] ??= ['count' => 0, 'cents' => 0];
            ++$stats[$key]['count'];
            $stats[$key]['cents'] += $deal->value()->amount;
        }

        return $stats;
    }

    public function countAll(): int
    {
        return \count($this->deals);
    }

    public function countByStage(Stage $stage): int
    {
        return \count(array_filter(
            $this->deals,
            static fn (Deal $deal): bool => $deal->stage() === $stage,
        ));
    }

    /**
     * @param list<Deal> $deals
     *
     * @return list<Deal>
     */
    private static function sorted(array $deals): array
    {
        usort(
            $deals,
            static fn (Deal $a, Deal $b): int => [$b->value()->amount, $a->title()] <=> [$a->value()->amount, $b->title()],
        );

        return $deals;
    }
}
