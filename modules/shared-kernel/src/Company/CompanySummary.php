<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Company;

/**
 * Die Sicht anderer Module auf eine Firma.
 *
 * Wie {@see \Crm\SharedKernel\User\UserSummary} bewusst eine flache Kopie:
 * ein Modul, das nur einen Firmennamen anzeigen will, soll nicht am
 * Doctrine-Mapping des company-Moduls haengen.
 */
final readonly class CompanySummary
{
    /**
     * @param string      $id       UUID als String - skalar, weil ueber
     *                              Modulgrenzen keine Association geht.
     * @param string|null $industry Branche, fuer Auswertungen.
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $industry = null,
        public ?string $city = null,
    ) {
    }
}
