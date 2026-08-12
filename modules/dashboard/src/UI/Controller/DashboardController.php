<?php

declare(strict_types=1);

namespace Crm\Dashboard\UI\Controller;

use Crm\SharedKernel\Dashboard\MetricRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bewusst kein Live-Component: hier gibt es nichts zu tippen und nichts zu
 * filtern. Eine Live-Component waere Ballast - sie brauchte einen
 * Checksum-Roundtrip fuer eine Seite, die sich nur beim Neuladen aendert.
 */
#[Route('/dashboard', name: 'dashboard_')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly MetricRegistry $metrics,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@DashboardModule/dashboard/index.html.twig', [
            'metrics' => $this->metrics->all(),
            'notable' => $this->metrics->notable(),
        ]);
    }
}
