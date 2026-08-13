<?php

declare(strict_types=1);

namespace App\Controller;

use Crm\SharedKernel\Menu\MenuRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The home page redirects to the first menu entry.
 *
 * That gives a fixed target for "after sign-in" and for "/" without the core
 * or a module having to name a particular other module. What is installed is
 * decided by the registry.
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
            // No module installed. Not an error - just nothing to show.
            return $this->render('home/empty.html.twig');
        }

        return $this->redirectToRoute($items[0]->route);
    }
}
