<?php

declare(strict_types=1);

namespace Crm\SharedKernel\User;

/**
 * Default implementation for as long as no user module is installed.
 *
 * The point: a module that wants to display user names can inject
 * UserFinderInterface without a second thought. If the user module is absent it
 * gets empty answers instead of a "Service not found" at container build time.
 *
 * Callers therefore have to expect an unresolvable user anyway - and that is
 * exactly the right expectation, because even with the user module an ID can be
 * out of date.
 */
final class NullUserFinder implements UserFinderInterface
{
    public function find(string $id): ?UserSummary
    {
        return null;
    }

    public function findMany(array $ids): array
    {
        return [];
    }

    public function findAllActive(): array
    {
        return [];
    }
}
