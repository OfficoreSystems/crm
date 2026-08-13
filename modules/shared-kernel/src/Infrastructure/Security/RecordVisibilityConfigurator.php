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
 * Schaltet den Sichtbarkeitsfilter fuer jeden Request scharf.
 *
 * Der Filter selbst kennt weder Symfony noch den angemeldeten Benutzer - er
 * bekommt Parameter. Diese Klasse besorgt sie: Benutzer-ID, Team und den Scope
 * je Modul, das ueberhaupt Besitzverhaeltnisse kennt.
 *
 * Ohne angemeldeten Benutzer bleibt der Filter aus. Das ist kein Loch: die
 * Firewall laesst dann ohnehin niemanden auf die Seiten, und Konsolenbefehle
 * und Migrationen muessen ungehindert arbeiten koennen.
 *
 * Diese Klasse muss als Dienst registriert sein - siehe config/services.php.
 * Fehlt sie dort, bleibt der Filter dauerhaft aus, und das faellt beim
 * Ausprobieren nicht auf: die Seiten funktionieren, sie zeigen nur zu viel.
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

        // Fuer jedes Modul, das Besitzverhaeltnisse kennt, den Lese-Scope
        // hinterlegen. Der Filter schlaegt dort nach und weiss so, ob er
        // einschraenken muss - ohne die Matrix selbst zu kennen.
        foreach ($this->ownership->knownModules() as $module) {
            // Kein Eintrag in der Matrix bedeutet den engsten Scope, nicht den
            // weitesten. Ein vergessenes Modul soll zu wenig zeigen, nicht zu
            // viel.
            $scope = $this->matrix->scopeFor($actor->actorRoles(), $module, Action::VIEW) ?? AccessScope::OWN;

            $filter->setParameter('scope_'.$module, $scope->value);
        }
    }
}
