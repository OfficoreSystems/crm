<?php

declare(strict_types=1);

namespace Crm\Contact\Application;

/**
 * Eingabe fuer {@see CreateContact}.
 */
final readonly class CreateContactCommand
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $company = null,
    ) {
    }
}
