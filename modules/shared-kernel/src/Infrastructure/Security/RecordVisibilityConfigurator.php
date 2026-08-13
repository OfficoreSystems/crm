<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Infrastructure\Security;

use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\Action;
use Crm\SharedKernel\Security\ActorInterface;
use Crm\SharedKernel\Security\OwnershipRegistry;
use Crm\SharedKernel\Security\PermissionMatrix;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Arms the visibility filter for every request.
 *
 * The filter itself knows neither Symfony nor the signed-in user - it receives
 * parameters. This class obtains them: user ID, team, and the scope for every
 * module that knows about ownership at all.
 *
 * Without a signed-in user the filter stays off. That is not a hole: the
 * firewall lets nobody onto the pages then anyway, and console commands and
 * migrations have to be able to work unhindered.
 *
 * This class has to be registered as a service - see config/services.php. If it
 * is missing there, the filter stays off permanently, and that does not show up
 * when trying things out: the pages work, they just show too much.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class RecordVisibilityConfigurator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private PermissionMatrix $matrix,
        private OwnershipRegistry $ownership,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->entityManager->getFilters();
        $actor = $this->security->getUser();

        if (!$actor instanceof ActorInterface) {
            if ($filters->isEnabled(RecordVisibilityFilter::NAME)) {
                $filters->disable(RecordVisibilityFilter::NAME);
            }

            return;
        }

        $filter = $filters->isEnabled(RecordVisibilityFilter::NAME)
            ? $filters->getFilter(RecordVisibilityFilter::NAME)
            : $filters->enable(RecordVisibilityFilter::NAME);

        \assert($filter instanceof RecordVisibilityFilter);

        $filter->useRestrictions($this->ownership->restrictions());
        $filter->setParameter('actor_id', $actor->actorId());
        $filter->setParameter('actor_team_id', $actor->actorTeamId() ?? '');

        // Record the read scope for every module that knows about ownership.
        // The filter looks it up there and thus knows whether it has to
        // restrict - without knowing the matrix itself.
        foreach ($this->ownership->knownModules() as $module) {
            // No entry in the matrix means the narrowest scope, not the widest.
            // A forgotten module should show too little, not too much.
            $scope = $this->matrix->scopeFor($actor->actorRoles(), $module, Action::VIEW) ?? AccessScope::OWN;

            $filter->setParameter('scope_'.$module, $scope->value);
        }
    }
}
