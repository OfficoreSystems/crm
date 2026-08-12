<?php

declare(strict_types=1);

namespace Crm\SharedKernel\User;

/**
 * Extension-Point: Benutzer nachschlagen, ohne das user-Modul zu kennen.
 *
 * Nur Lesezugriff. Wer Benutzer anlegen oder aendern will, gehoert ins
 * user-Modul - andere Module bekommen hier absichtlich keinen Schreibweg.
 *
 * Die Standardimplementierung ist {@see NullUserFinder}. Ist das user-Modul
 * installiert, ueberschreibt es den Alias mit seiner eigenen. Dadurch bleibt
 * die Anwendung auch ohne das Modul startfaehig.
 */
interface UserFinderInterface
{
    public function find(string $id): ?UserSummary;

    /**
     * @param list<string> $ids
     *
     * @return array<string, UserSummary> Indiziert nach ID. Unbekannte IDs
     *                                    fehlen im Ergebnis, statt null zu sein.
     */
    public function findMany(array $ids): array;

    /**
     * @return list<UserSummary>
     */
    public function findAllActive(): array;
}
