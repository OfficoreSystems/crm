<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Infrastructure\Localization;

use Crm\SharedKernel\Localization\Locale;
use Crm\SharedKernel\Security\ActorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Stellt die Sprache auf die des angemeldeten Benutzers um.
 *
 * Zur Priority: Symfonys eigener LocaleAwareListener laeuft bei 15 und traegt
 * die Sprache des Requests in Uebersetzer und Formatierer. Der Firewall-
 * Listener laeuft bei 8 - vorher gibt es keinen angemeldeten Benutzer, an dem
 * sich etwas ablesen liesse.
 *
 * Diese Klasse muss also *nach* der Firewall laufen und kommt damit zu spaet
 * fuer LocaleAwareListener. Deshalb setzt sie beides selbst: den Request (fuer
 * alles, was ihn spaeter fragt) und den LocaleSwitcher, der die
 * locale-aware-Dienste nachzieht. Nur eines von beidem zu setzen ergibt eine
 * Anwendung, die halb uebersetzt ist - und zwar je nach Stelle unterschiedlich.
 *
 * Ohne angemeldeten Benutzer passiert nichts: dann gilt default_locale.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final readonly class ActorLocaleListener
{
    public function __construct(
        private Security $security,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $actor = $this->security->getUser();

        if (!$actor instanceof ActorInterface) {
            return;
        }

        $locale = ($actor->actorLocale() ?? Locale::default())->value;

        $event->getRequest()->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
    }
}
