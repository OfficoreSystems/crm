<?php

declare(strict_types=1);

namespace Crm\SharedKernel\User;

/**
 * Standardimplementierung, solange kein user-Modul installiert ist.
 *
 * Der Sinn: ein Modul, das Benutzernamen anzeigen moechte, kann
 * UserFinderInterface bedenkenlos injizieren. Fehlt das user-Modul, bekommt es
 * leere Antworten statt eines "Service not found" beim Container-Build.
 *
 * Aufrufer muessen also ohnehin damit rechnen, dass ein Benutzer nicht
 * aufloesbar ist - und genau das ist die richtige Erwartung, denn auch mit
 * user-Modul kann eine ID veraltet sein.
 */
final class NullUserFinder implements UserFinderInterface
{
    public function find(string $id): ?UserSummary
    {
        return null;
    }

    public function findMany(array $ids): array
    {
        return [];
    }

    public function findAllActive(): array
    {
        return [];
    }
}
