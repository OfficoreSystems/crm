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
 * Switches the language to that of the signed-in user.
 *
 * On the priority: Symfony's own LocaleAwareListener runs at 15 and carries the
 * request's language into translator and formatters. The firewall listener runs
 * at 8 - before that there is no signed-in user to read anything off.
 *
 * This class therefore has to run *after* the firewall and is thus too late for
 * LocaleAwareListener. That is why it sets both itself: the request (for
 * everything that asks it later) and the LocaleSwitcher, which pulls the
 * locale-aware services along. Setting only one of the two produces an
 * application that is half translated - and differently so depending on where
 * you look.
 *
 * Without a signed-in user nothing happens: default_locale applies then.
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
