<?php

declare(strict_types=1);

namespace Crm\Deal\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/deals', name: 'deal_')]
final class DealController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@DealModule/deal/index.html.twig');
    }
}
