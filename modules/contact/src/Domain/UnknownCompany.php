<?php

declare(strict_types=1);

namespace Crm\Contact\Domain;

final class UnknownCompany extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Es gibt keine Firma mit der ID %s.', $id));
    }
}
