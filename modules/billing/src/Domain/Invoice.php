<?php

declare(strict_types=1);

namespace Crm\Billing\Domain;

final class Invoice
{
    public function __construct(public readonly string $number)
    {
    }
}
