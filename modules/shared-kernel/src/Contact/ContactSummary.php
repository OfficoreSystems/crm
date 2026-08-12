<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Contact;

/**
 * Die Sicht anderer Module auf einen Kontakt.
 */
final readonly class ContactSummary
{
    public function __construct(
        public string $id,
        public string $fullName,
        public ?string $email = null,
        public ?string $companyId = null,
    ) {
    }
}
