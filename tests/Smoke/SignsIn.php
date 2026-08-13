<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\User\Application\CreateTeam;
use Crm\User\Application\CreateUser;
use Crm\User\Application\CreateUserCommand;
use Crm\User\Domain\Role;
use Crm\User\Domain\User;
use Crm\User\Infrastructure\Security\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Anmelden fuer Funktionstests auf Anwendungsebene.
 *
 * Warum hier und nicht im Modul: das user-Modul zu kennen ist auf dieser Ebene
 * erlaubt - hier wird die zusammengesetzte Anwendung geprueft. Ein Test im
 * deal-Modul duerfte das nicht, er waere sonst ohne das user-Modul nicht mehr
 * lauffaehig.
 */
trait SignsIn
{
    /**
     * Aufraeumen per DELETE, nicht per Transaktion.
     *
     * Bei WebTestCase startet jeder Request den Kernel neu und damit eine
     * eigene Verbindung. Eine im Test geoeffnete Transaktion waere fuer den
     * Request unsichtbar - der angelegte Benutzer existierte fuer die
     * Anmeldung schlicht nicht.
     *
     * @param list<string> $extraTables Modultabellen, in umgekehrter
     *                                  Abhaengigkeitsreihenfolge.
     */
    private function purge(array $extraTables = []): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();

        foreach ([...$extraTables, 'user_users', 'user_teams'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
    }

    /**
     * @param list<Role> $roles
     */
    private function givenUser(
        string $email,
        string $name,
        array $roles = [],
        ?string $teamName = null,
    ): User {
        $team = null === $teamName
            ? null
            : (static::getContainer()->get(CreateTeam::class))($teamName);

        return (static::getContainer()->get(CreateUser::class))(new CreateUserCommand(
            email: $email,
            name: $name,
            plainPassword: 'ein-hinreichend-langes-passwort',
            roles: $roles,
            teamId: $team?->id(),
        ));
    }

    /**
     * Anmelden - und dabei die Identity Map leeren.
     *
     * Das Leeren ist kein Beiwerk, sondern notwendig: der KernelBrowser startet
     * den Kernel vor dem *ersten* Request nicht neu. Der EntityManager des
     * Tests ist damit derselbe, den der Request benutzt, und ein soeben
     * angelegter Datensatz liegt noch in seiner Identity Map. find() liefert
     * ihn dann aus dem Speicher - ohne SQL und damit ohne Sichtbarkeitsfilter.
     *
     * Der Test saehe grün aus, wo die Anwendung filtert, und rot, wo sie es
     * nicht tut. Genau andersherum als gewuenscht.
     */
    private function signIn(KernelBrowser $client, User $user): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        $client->loginUser(SecurityUser::fromDomain($user));
    }
}
