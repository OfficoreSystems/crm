<?php

declare(strict_types=1);

namespace Crm\Deal\UI\Controller;

use Crm\Deal\Domain\Deal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/deals', name: 'deal_')]
final class DealController extends AbstractController
{
    /**
     * Modulweite Pruefung: darf der Benutzer Verkaufschancen ueberhaupt sehen?
     * *Welche* er bekommt, entscheidet der Doctrine-Filter - nicht dieser
     * Aufruf. Ein Voter je Zeile skaliert nicht, und die Zeilen waeren zu dem
     * Zeitpunkt bereits geladen.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('deal.view')]
    public function index(): Response
    {
        return $this->render('@DealModule/deal/index.html.twig');
    }

    /**
     * Pruefung auf dem Datensatz selbst.
     *
     * Hier bekommt der Voter das Objekt und vergleicht Besitzer und Team. Ein
     * Kollege aus einem anderen Team bekommt 403 - nicht etwa eine leere
     * Seite.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('deal.view', subject: 'deal')]
    public function show(Deal $deal): Response
    {
        return $this->render('@DealModule/deal/show.html.twig', ['deal' => $deal]);
    }
}
