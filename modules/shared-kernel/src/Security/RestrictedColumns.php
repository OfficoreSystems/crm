<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Where a module stores owner and team.
 *
 * The voter decides about individual records. For lists it is no use: a page
 * with fifty rows would vote fifty times, and worse - by then the rows would
 * already be loaded from the database. Whoever must not see other people's data
 * should not receive it in the first place.
 *
 * For that the visibility filter needs column names. They live here and not as
 * an attribute on the entity: a module's domain layer depends on nothing, and an
 * attribute from the shared kernel would be exactly such a dependency. They also
 * sit with the ownership provider, that is in the same place as the answer to
 * "who owns this record" - two places for it would be two places that can drift
 * apart.
 */
final readonly class RestrictedColumns
{
    /**
     * @param class-string $entityClass The entity whose queries the filter
     *                                  attaches itself to.
     * @param string       $ownerColumn Column name, not field name - the filter
     *                                  writes SQL.
     */
    public function __construct(
        public string $entityClass,
        public string $ownerColumn = 'owner_id',
        public string $teamColumn = 'owner_team_id',
    ) {
    }
}
