<?php

declare(strict_types=1);

namespace Crm\User\UI\Controller;

use Crm\SharedKernel\Localization\Locale;
use Crm\SharedKernel\Security\ActorInterface;
use Crm\User\Application\SwitchLanguage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Der Sprachumschalter.
 *
 * Bewusst ohne #[IsGranted]: seine eigene Anzeigesprache darf jeder aendern,
 * der angemeldet ist. Ein Eintrag in der Rechtematrix waere ein Recht, das man
 * jemandem entziehen koennte - und ein Konto, dessen Oberflaeche man nicht
 * lesen kann und nicht umstellen darf, waere eine merkwuerdige Strafe.
 *
 * POST und nicht GET: der Aufruf aendert etwas. Ein Link im Menue waere von
 * jedem Bild-Tag aus ausloesbar - harmlos, aber laestig.
 */
#[Route('/settings/language', name: 'user_language_')]
final class LanguageController extends AbstractController
{
    #[Route('', name: 'switch', methods: ['POST'])]
    public function switch(Request $request, SwitchLanguage $switchLanguage): Response
    {
        $actor = $this->getUser();

        if (!$actor instanceof ActorInterface || !Uuid::isValid($actor->actorId())) {
            throw $this->createAccessDeniedException();
        }

        $userId = Uuid::fromString($actor->actorId());

        if (!$this->isCsrfTokenValid('user_language_'.$userId, (string) $request->request->get('_token'))) {
            return $this->back($request);
        }

        $locale = Locale::tryFrom((string) $request->request->get('locale'));

        if (null !== $locale) {
            ($switchLanguage)($userId, $locale);
        }

        return $this->back($request);
    }

    /**
     * Zurueck, wo der Benutzer war.
     *
     * Der Umschalter steht in der Navigation und damit auf jeder Seite. Ihn
     * immer auf die Startseite zurueckwerfen zu lassen waere der schnellste
     * Weg, ihn unbenutzbar zu machen.
     *
     * Nur der Pfad, nie die volle URL aus dem Referer: ein fremder Host dort
     * waere eine offene Weiterleitung.
     */
    private function back(Request $request): Response
    {
        $target = (string) $request->request->get('_back', '/');

        if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
            $target = '/';
        }

        return $this->redirect($target);
    }
}
