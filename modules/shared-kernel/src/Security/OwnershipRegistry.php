<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Finds the module responsible for a record and its ownership details.
 */
final class OwnershipRegistry
{
    /**
     * @var array<string, RecordOwnershipInterface>|null class name => provider
     */
    private ?array $byClass = null;

    /**
     * @param iterable<RecordOwnershipInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    public function supports(object $record): bool
    {
        return null !== $this->providerFor($record);
    }

    /**
     * The module the record belongs to - or null when nobody feels
     * responsible.
     */
    public function moduleOf(object $record): ?string
    {
        return $this->providerFor($record)?->module();
    }

    /**
     * Without a responsible provider the record counts as nobody's property.
     * It is then reachable only with ALL rights - the safe default.
     */
    public function ownershipOf(object $record): RecordOwnership
    {
        return $this->providerFor($record)?->ownershipOf($record) ?? RecordOwnership::nobody();
    }

    /**
     * @return list<string>
     */
    public function knownModules(): array
    {
        $modules = [];

        foreach ($this->providers as $provider) {
            $modules[$provider->module()] = true;
        }

        return array_keys($modules);
    }

    /**
     * What the visibility filter needs, per entity class.
     *
     * @return array<class-string, RecordRestriction>
     */
    public function restrictions(): array
    {
        $restrictions = [];

        foreach ($this->providers as $provider) {
            $columns = $provider->restrictedColumns();

            if (null === $columns) {
                continue;
            }

            $restrictions[$columns->entityClass] = new RecordRestriction(
                module: $provider->module(),
                ownerColumn: $columns->ownerColumn,
                teamColumn: $columns->teamColumn,
            );
        }

        return $restrictions;
    }

    private function providerFor(object $record): ?RecordOwnershipInterface
    {
        // Cache by class name: otherwise supports() would run through every
        // provider for each row of a list.
        $class = $record::class;

        if (isset($this->byClass[$class])) {
            return $this->byClass[$class];
        }

        foreach ($this->providers as $provider) {
            if ($provider->supports($record)) {
                return $this->byClass[$class] = $provider;
            }
        }

        return null;
    }
}
