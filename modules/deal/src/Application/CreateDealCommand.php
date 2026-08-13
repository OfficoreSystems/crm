<?php

declare(strict_types=1);

namespace Crm\Deal\Application;

use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Symfony\Component\Uid\Uuid;

final readonly class CreateDealCommand
{
    public function __construct(
        public string $title,
        public ?Money $value = null,
        public ?Stage $stage = null,
        public ?Uuid $companyId = null,
        public ?Uuid $contactId = null,
        public ?Uuid $ownerId = null,
        public ?Uuid $ownerTeamId = null,
        public ?\DateTimeImmutable $expectedCloseDate = null,
    ) {
    }
}
