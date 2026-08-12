<?php

declare(strict_types=1);

namespace Crm\User\Domain;

final class EmailAlreadyInUse extends \DomainException
{
    public static function for(string $email): self
    {
        return new self(sprintf('Es gibt bereits einen Benutzer mit der Adresse "%s".', $email));
    }
}
