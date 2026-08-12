<?php

declare(strict_types=1);

namespace Crm\User\Domain;

use Symfony\Component\Uid\Uuid;

final class TeamNotFound extends \DomainException
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Kein Team mit der ID %s.', $id));
    }
}
