<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Was der Sichtbarkeitsfilter je Entity wissen muss.
 *
 * Entsteht in {@see OwnershipRegistry::restrictions()} aus dem Modulnamen des
 * Anbieters und seinen {@see RestrictedColumns}. Der Filter bekommt damit
 * alles, was er braucht, ohne ein Modul oder die Rechtematrix zu kennen.
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
