<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Vorgabe-Benutzerquelle: kennt niemanden.
 *
 * Sie existiert wegen einer Eigenheit von Symfony: `security.firewalls` ist
 * ein prototypisierter Knoten und muss vollstaendig aus *einer* Konfigurations-
 * datei kommen. Ein Modul kann die Firewall also nicht per prependExtension()
 * ergaenzen - der Container-Build bricht mit "You are not allowed to define new
 * elements for path security.firewalls" ab.
 *
 * Also definiert der Core die Firewall einmal und verweist auf die feste
 * Service-ID `crm.security.user_provider`. Diese Klasse ist der Vorgabewert
 * dahinter; das user-Modul ueberschreibt den Alias mit seiner eigenen
 * Implementierung. Der Core nennt damit weiterhin kein Modul, und ohne
 * user-Modul startet die Anwendung - es kann sich dann nur niemand anmelden.
 *
 * @implements UserProviderInterface<UserInterface>
 */
final class NullUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('Es ist kein Modul installiert, das Benutzer bereitstellt.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException('Es ist kein Modul installiert, das Benutzer bereitstellt.');
    }

    public function supportsClass(string $class): bool
    {
        return false;
    }
}
