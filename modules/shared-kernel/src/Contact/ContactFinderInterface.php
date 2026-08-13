<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Contact;

/**
 * Extension point: look up contacts without knowing the contact module.
 *
 * Read access only. The default implementation is {@see NullContactFinder}.
 */
interface ContactFinderInterface
{
    public function find(string $id): ?ContactSummary;

    /**
     * @param list<string> $ids
     *
     * @return array<string, ContactSummary> Indexed by ID.
     */
    public function findMany(array $ids): array;

    /**
     * @return list<ContactSummary>
     */
    public function searchByName(string $query, int $limit = 25): array;

    public function exists(string $id): bool;
}
