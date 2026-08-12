<?php

declare(strict_types=1);

namespace Crm\Activity\Application;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityRepositoryInterface;

final readonly class CompleteTask
{
    public function __construct(
        private ActivityRepositoryInterface $activities,
    ) {
    }

    public function __invoke(Activity $activity, ?\DateTimeImmutable $at = null): Activity
    {
        $activity->complete($at);
        $this->activities->save($activity);

        return $activity;
    }
}
