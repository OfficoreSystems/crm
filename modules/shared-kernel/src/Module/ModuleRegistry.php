<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Module;

/**
 * Verzeichnis aller installierten Module.
 *
 * Macht den Tag `crm.module` nutzbar: ohne eine Abfragestelle waere die
 * Selbstbeschreibung der Module nur Dekoration.
 */
final class ModuleRegistry
{
    /**
     * @var array<string, CrmModuleInterface>|null
     */
    private ?array $byName = null;

    /**
     * @param iterable<CrmModuleInterface> $modules
     */
    public function __construct(
        private readonly iterable $modules,
    ) {
    }

    /**
     * @return array<string, CrmModuleInterface> Indiziert nach Modulnamen.
     */
    public function all(): array
    {
        if (null === $this->byName) {
            $this->byName = [];

            foreach ($this->modules as $module) {
                $this->byName[$module->name()] = $module;
            }
        }

        return $this->byName;
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name): ?CrmModuleInterface
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * Module, deren deklarierte Abhaengigkeiten nicht installiert sind.
     *
     * @return array<string, list<string>> Modulname => fehlende Abhaengigkeiten.
     */
    public function missingDependencies(): array
    {
        $missing = [];

        foreach ($this->all() as $name => $module) {
            $gaps = array_values(array_filter(
                $module->dependencies(),
                fn (string $dependency): bool => !$this->has($dependency),
            ));

            if ([] !== $gaps) {
                $missing[$name] = $gaps;
            }
        }

        return $missing;
    }
}
