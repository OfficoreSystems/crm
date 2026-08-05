<?php

declare(strict_types=1);

namespace Crm\Contact\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contacts', name: 'contact_')]
final class ContactController extends AbstractController
{
    /**
     * Die Seite ist bewusst duenn: die Liste samt Suche steckt komplett in der
     * Live-Component, damit sie ohne Controller-Roundtrip aktualisiert.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@ContactModule/contact/index.html.twig');
    }
}
