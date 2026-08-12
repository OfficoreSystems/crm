<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Contact;

/**
 * Extension-Point: Kontakte nachschlagen, ohne das contact-Modul zu kennen.
 *
 * Nur Lesezugriff. Standardimplementierung ist {@see NullContactFinder}.
 */
interface ContactFinderInterface
{
    public function find(string $id): ?ContactSummary;

    /**
     * @param list<string> $ids
     *
     * @return array<string, ContactSummary> Indiziert nach ID.
     */
    public function findMany(array $ids): array;

    /**
     * @return list<ContactSummary>
     */
    public function searchByName(string $query, int $limit = 25): array;

    public function exists(string $id): bool;
}
