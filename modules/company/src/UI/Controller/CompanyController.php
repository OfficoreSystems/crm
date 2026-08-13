<?php

declare(strict_types=1);

namespace Crm\Company\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/companies', name: 'company_')]
final class CompanyController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('company.view')]
    public function index(): Response
    {
        return $this->render('@CompanyModule/company/index.html.twig');
    }
}
