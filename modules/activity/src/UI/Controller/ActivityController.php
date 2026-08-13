<?php

declare(strict_types=1);

namespace Crm\Activity\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activities', name: 'activity_')]
final class ActivityController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('activity.view')]
    public function index(): Response
    {
        return $this->render('@ActivityModule/activity/index.html.twig');
    }
}
