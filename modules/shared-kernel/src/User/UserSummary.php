<?php

declare(strict_types=1);

namespace Crm\SharedKernel\User;

/**
 * How other modules see a user.
 *
 * Deliberately a flat copy and not the User entity: otherwise every module that
 * only wants to display a name would depend on the Doctrine mapping of the user
 * module - and would stop working without it.
 */
final readonly class UserSummary
{
    /**
     * @param string      $id     UUID as a string. Scalar, because no Doctrine
     *                            association crosses module boundaries.
     * @param string|null $teamId UUID of the team, or null.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $teamId = null,
        public bool $active = true,
    ) {
    }
}
