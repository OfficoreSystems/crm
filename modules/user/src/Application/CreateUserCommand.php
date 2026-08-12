<?php

declare(strict_types=1);

namespace Crm\User\Application;

use Crm\User\Domain\Role;
use Symfony\Component\Uid\Uuid;

final readonly class CreateUserCommand
{
    /**
     * @param list<Role> $roles ROLE_USER wird immer ergaenzt.
     */
    public function __construct(
        public string $email,
        public string $name,
        public string $plainPassword,
        public array $roles = [],
        public ?Uuid $teamId = null,
    ) {
    }
}
