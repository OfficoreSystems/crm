<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Contact;

/**
 * Standardimplementierung, solange kein contact-Modul installiert ist.
 */
final class NullContactFinder implements ContactFinderInterface
{
    public function find(string $id): ?ContactSummary
    {
        return null;
    }

    public function findMany(array $ids): array
    {
        return [];
    }

    public function searchByName(string $query, int $limit = 25): array
    {
        return [];
    }

    public function exists(string $id): bool
    {
        return false;
    }
}
