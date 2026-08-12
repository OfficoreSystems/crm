<?php

declare(strict_types=1);

namespace Crm\Search\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/search', name: 'search_')]
final class SearchController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@SearchModule/search/index.html.twig');
    }
}
