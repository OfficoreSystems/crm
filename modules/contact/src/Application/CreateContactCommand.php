<?php

declare(strict_types=1);

namespace Crm\Contact\Application;

use Symfony\Component\Uid\Uuid;

/**
 * Eingabe fuer {@see CreateContact}.
 */
final readonly class CreateContactCommand
{
    /**
     * @param Uuid|null $companyId Wird nicht auf Existenz geprueft - siehe
     *                             {@see CreateContact}. Fuer geprueftes
     *                             Zuordnen gibt es {@see AssignContactToCompany}.
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?Uuid $companyId = null,
    ) {
    }
}
