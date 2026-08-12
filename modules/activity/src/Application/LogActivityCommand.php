<?php

declare(strict_types=1);

namespace Crm\Activity\Application;

use Crm\Activity\Domain\ActivityType;
use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

final readonly class LogActivityCommand
{
    public function __construct(
        public ActivityType $type,
        public SubjectRef $subject,
        public string $summary,
        public ?string $body = null,
        public ?Uuid $authorId = null,
        public ?\DateTimeImmutable $occurredAt = null,
    ) {
    }
}
