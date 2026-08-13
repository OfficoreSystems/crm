<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * What the visibility filter needs to know per entity.
 *
 * Created in {@see OwnershipRegistry::restrictions()} from the provider's module
 * name and its {@see RestrictedColumns}. The filter thereby gets everything it
 * needs without knowing a module or the permission matrix.
 */
final readonly class RecordRestriction
{
    public function __construct(
        public string $module,
        public string $ownerColumn,
        public string $teamColumn,
    ) {
    }
}
