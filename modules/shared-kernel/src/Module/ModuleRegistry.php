<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Module;

/**
 * Directory of all installed modules.
 *
 * Makes the tag `crm.module` usable: without somewhere to query it, the modules'
 * self-description would be decoration.
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
     * @return array<string, CrmModuleInterface> Indexed by module name.
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
     * Modules whose declared dependencies are not installed.
     *
     * @return array<string, list<string>> module name => missing dependencies.
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
