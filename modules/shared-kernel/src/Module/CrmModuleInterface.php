<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Module;

/**
 * Extension point: a module describes itself.
 *
 * This makes it possible to answer at runtime what is installed - necessary as
 * soon as modules can be added as third-party plugins.
 *
 * Implementations are tagged with `crm.module` automatically through
 * registerForAutoconfiguration().
 */
interface CrmModuleInterface
{
    /**
     * Technical name, lower case and stable, "contact" for instance.
     * Used as a key - renaming it is a breaking change.
     */
    public function name(): string;

    public function version(): string;

    /**
     * Names of the modules without which this module does not work.
     *
     * @return list<string>
     */
    public function dependencies(): array;
}
