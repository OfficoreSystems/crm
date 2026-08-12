<?php

declare(strict_types=1);

namespace App\Controller;

use Crm\SharedKernel\Menu\MenuRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Die Startseite leitet auf den ersten Menueeintrag weiter.
 *
 * Damit gibt es ein festes Ziel fuer "nach dem Login" und fuer "/", ohne dass
 * der Core oder ein Modul ein bestimmtes anderes Modul benennen muesste. Was
 * installiert ist, entscheidet die Registry.
 */
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly MenuRegistry $menu,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        $items = $this->menu->items();

        if ([] === $items) {
            // Kein Modul installiert. Kein Fehler - nur nichts zu zeigen.
            return $this->render('home/empty.html.twig');
        }

        return $this->redirectToRoute($items[0]->route);
    }
}
