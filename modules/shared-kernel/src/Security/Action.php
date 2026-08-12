<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Die vier Aktionen, fuer die es Rechte gibt.
 *
 * Bewusst genau vier und nicht beliebig erweiterbar: sobald jedes Modul eigene
 * Aktionen mitbringt ("exportieren", "zuweisen", "abschliessen"), ist die
 * Rechtematrix nicht mehr ueberblickbar und niemand kann mehr sagen, was eine
 * Rolle eigentlich darf. Spezialfaelle gehoeren als Rolle modelliert, nicht
 * als neue Aktion.
 */
enum Action: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case EDIT = 'edit';
    case DELETE = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'ansehen',
            self::CREATE => 'anlegen',
            self::EDIT => 'bearbeiten',
            self::DELETE => 'löschen',
        };
    }

    /**
     * Aktionen, die einen konkreten Datensatz brauchen.
     *
     * "create" ist die Ausnahme: dort gibt es noch nichts, dessen Besitzer man
     * pruefen koennte.
     */
    public function needsRecord(): bool
    {
        return self::CREATE !== $this;
    }
}
