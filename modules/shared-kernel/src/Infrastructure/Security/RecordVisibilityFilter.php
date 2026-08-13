<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Infrastructure\Security;

use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\RecordRestriction;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Restricts lists directly in SQL.
 *
 * The voter answers "may this user have *this* record". For a list that is the
 * wrong question: it would be asked per row, and by then the rows would already
 * be loaded. Whoever must not see other people's deals should not get them out
 * of the database in the first place.
 *
 * The class lives in Infrastructure and not next to the voter: it inherits from
 * Doctrine, and the contract part of the shared kernel is meant to stay free of
 * persistence. Changing something here changes infrastructure - not the
 * contract.
 *
 * The filter does nothing until it has been enabled *and* parameterised. Both is
 * done by {@see RecordVisibilityConfigurator} per request. Without a signed-in
 * user it stays off, so that console commands and migrations can work
 * unhindered.
 */
final class RecordVisibilityFilter extends SQLFilter
{
    public const NAME = 'crm_record_visibility';

    /**
     * @var array<class-string, RecordRestriction>
     */
    private array $restrictions = [];

    /**
     * Set by the configurator after Doctrine has built the filter.
     *
     * Doctrine creates filters without a container, hence this route instead of
     * constructor injection.
     *
     * @param array<class-string, RecordRestriction> $restrictions
     */
    public function useRestrictions(array $restrictions): void
    {
        $this->restrictions = $restrictions;
    }

    /**
     * @param ClassMetadata<object> $targetEntity
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Entities without an entry are not filtered - master data such as
        // companies and contacts belongs to everyone.
        $restriction = $this->restrictions[$targetEntity->getName()] ?? null;

        if (null === $restriction) {
            return '';
        }

        $scope = $this->scopeFor($restriction->module);

        if (null === $scope || AccessScope::ALL === $scope) {
            return '';
        }

        $actorId = $this->readParameter('actor_id');

        if (null === $actorId) {
            return '';
        }

        $owner = sprintf('%s.%s = %s', $targetTableAlias, $restriction->ownerColumn, $actorId);

        if (AccessScope::OWN === $scope) {
            return $owner;
        }

        $teamId = $this->readParameter('actor_team_id');

        // Without a team, TEAM collapses to OWN. Otherwise a user without a
        // team would see every record without a team - that is, those of every
        // other user without a team.
        if (null === $teamId) {
            return $owner;
        }

        return sprintf(
            '(%s OR %s.%s = %s)',
            $owner,
            $targetTableAlias,
            $restriction->teamColumn,
            $teamId,
        );
    }

    private function scopeFor(string $module): ?AccessScope
    {
        // Raw, not quoted: the value is compared, not embedded into SQL. With
        // quotes tryFrom() would fail silently and the filter would let
        // everything through.
        $raw = $this->readRawParameter('scope_'.$module) ?? $this->readRawParameter('scope_default');

        return null === $raw ? null : AccessScope::tryFrom($raw);
    }

    /**
     * Reads a parameter without throwing when it is missing.
     *
     * SQLFilter::getParameter() throws on missing parameters. That is exactly
     * the normal case here: on the console there is no signed-in user, and then
     * the filter should simply do nothing.
     */
    private function readParameter(string $name): ?string
    {
        if (!$this->hasParameter($name)) {
            return null;
        }

        // getParameter() returns the value already quoted - exactly right for
        // embedding into SQL.
        $value = $this->getParameter($name);

        return "''" === $value ? null : $value;
    }

    /**
     * Like readParameter, but without quoting - for values that get compared
     * rather than embedded.
     */
    private function readRawParameter(string $name): ?string
    {
        $quoted = $this->readParameter($name);

        return null === $quoted ? null : trim($quoted, "'");
    }
}
