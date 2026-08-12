<?php

declare(strict_types=1);

namespace Crm\Company\Application;

use Crm\Company\Domain\Address;

final readonly class CreateCompanyCommand
{
    public function __construct(
        public string $name,
        public ?string $industry = null,
        public ?string $website = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?Address $address = null,
    ) {
    }
}
