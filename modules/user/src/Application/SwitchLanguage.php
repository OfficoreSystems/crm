<?php

declare(strict_types=1);

namespace Crm\User\Application;

use Crm\SharedKernel\Localization\Locale;
use Crm\User\Domain\UserRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Use-Case: die Anzeigesprache eines Kontos aendern.
 *
 * Ein eigener Use-Case und kein Setter im Controller, aus einem Grund, der
 * spaeter zaehlt: die Sprache gilt nicht nur fuer die Oberflaeche, sondern
 * auch fuer Mails und Exporte. Wer sie einmal an einer zentralen Stelle
 * aendert, findet spaeter die Stelle wieder, an der weitere Wirkungen
 * anzuhaengen sind.
 */
final readonly class SwitchLanguage
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function __invoke(Uuid $userId, Locale $locale): void
    {
        $user = $this->users->find($userId);

        if (null === $user) {
            // Kein Fehler: der Benutzer ist angemeldet, aber sein Konto wurde
            // waehrend der Sitzung geloescht. Die naechste Anfrage wirft ihn
            // ohnehin heraus.
            return;
        }

        $user->switchTo($locale);
        $this->users->save($user);
    }
}
