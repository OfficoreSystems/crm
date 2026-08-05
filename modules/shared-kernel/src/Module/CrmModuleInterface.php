<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Module;

/**
 * Extension-Point: ein Modul beschreibt sich selbst.
 *
 * Damit laesst sich zur Laufzeit beantworten, was installiert ist - noetig,
 * sobald Module als Drittanbieter-Plugins nachinstalliert werden koennen.
 *
 * Implementierungen werden ueber registerForAutoconfiguration() automatisch
 * mit `crm.module` getaggt.
 */
interface CrmModuleInterface
{
    /**
     * Technischer Name, klein und stabil, z. B. "contact".
     * Wird als Schluessel verwendet - Umbenennen ist ein Breaking Change.
     */
    public function name(): string;

    public function version(): string;

    /**
     * Namen der Module, ohne die dieses Modul nicht funktioniert.
     *
     * @return list<string>
     */
    public function dependencies(): array;
}
