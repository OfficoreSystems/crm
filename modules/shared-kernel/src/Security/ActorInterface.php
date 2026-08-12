<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Wer gerade handelt.
 *
 * Der Voter im Shared Kernel muss Benutzer-ID und Team kennen, darf aber das
 * user-Modul nicht kennen. Deshalb dieser schmale Vertrag: das user-Modul
 * laesst seinen SecurityUser ihn implementieren, und der Voter prueft nur
 * dagegen.
 *
 * Ohne user-Modul gibt es keinen angemeldeten Benutzer - dann greift der Voter
 * ohnehin nicht.
 */
interface ActorInterface
{
    public function actorId(): string;

    /**
     * Das Team, oder null wenn keinem zugeordnet.
     *
     * Null ist bedeutsam: wer in keinem Team ist, sieht bei TEAM-Rechten nur
     * seine eigenen Daten. Alles andere waere ein Loch - sonst saehe ein
     * teamloser Benutzer die Daten aller anderen teamlosen Benutzer.
     */
    public function actorTeamId(): ?string;

    /**
     * @return list<string>
     */
    public function actorRoles(): array;
}
