<?php

declare(strict_types=1);

namespace Crm\Search\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/search', name: 'search_')]
final class SearchController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('search.view')]
    public function index(): Response
    {
        return $this->render('@SearchModule/search/index.html.twig');
    }
}
