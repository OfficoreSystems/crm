<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Findet zu einem Datensatz das zustaendige Modul und dessen Besitzangaben.
 */
final class OwnershipRegistry
{
    /**
     * @var array<string, RecordOwnershipInterface>|null Klassenname => Anbieter
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
     * Das Modul, zu dem der Datensatz gehoert - oder null, wenn sich niemand
     * zustaendig fuehlt.
     */
    public function moduleOf(object $record): ?string
    {
        return $this->providerFor($record)?->module();
    }

    /**
     * Ohne zustaendigen Anbieter gilt der Datensatz als niemandes Eigentum.
     * Damit ist er nur mit ALL-Rechten erreichbar - die sichere Vorgabe.
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
     * Was der Sichtbarkeitsfilter braucht, je Entity-Klasse.
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
        // Nach Klassennamen zwischenspeichern: supports() laeuft bei einer
        // Liste sonst je Zeile durch alle Anbieter.
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
