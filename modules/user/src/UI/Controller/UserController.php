<?php

declare(strict_types=1);

namespace Crm\User\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Geprueft wird ueber die Rechtematrix, nicht ueber eine Rolle direkt.
 *
 * Eine Rollenpruefung hier waere eine zweite Wahrheit neben der Matrix: wer
 * spaeter eine Rolle "Teamleitung" einfuehrt, muesste beide Stellen finden.
 * Dass die Benutzerverwaltung heute dem Administrator vorbehalten ist, steht
 * als leerer Modul-Eintrag fuer ROLE_USER in PermissionMatrix::default().
 */
#[Route('/users', name: 'user_')]
#[IsGranted('user.view')]
final class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@UserModule/user/index.html.twig');
    }
}
