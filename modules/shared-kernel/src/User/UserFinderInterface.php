<?php

declare(strict_types=1);

namespace Crm\SharedKernel\User;

/**
 * Extension point: look up users without knowing the user module.
 *
 * Read access only. Whoever wants to create or change users belongs in the user
 * module - other modules deliberately get no write path here.
 *
 * The default implementation is {@see NullUserFinder}. Once the user module is
 * installed it overrides the alias with its own. That keeps the application
 * bootable without the module too.
 */
interface UserFinderInterface
{
    public function find(string $id): ?UserSummary;

    /**
     * @param list<string> $ids
     *
     * @return array<string, UserSummary> Indexed by ID. Unknown IDs are missing
     *                                    from the result rather than being null.
     */
    public function findMany(array $ids): array;

    /**
     * @return list<UserSummary>
     */
    public function findAllActive(): array;
}
