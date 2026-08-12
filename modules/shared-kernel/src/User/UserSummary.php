<?php

declare(strict_types=1);

namespace Crm\SharedKernel\User;

/**
 * Die Sicht anderer Module auf einen Benutzer.
 *
 * Bewusst eine flache Kopie und nicht die User-Entity: sonst haenge jedes
 * Modul, das nur einen Namen anzeigen will, am Doctrine-Mapping des
 * user-Moduls - und waere ohne dieses nicht mehr lauffaehig.
 */
final readonly class UserSummary
{
    /**
     * @param string      $id     UUID als String. Skalar, weil ueber
     *                            Modulgrenzen keine Doctrine-Association geht.
     * @param string|null $teamId UUID des Teams, oder null.
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
