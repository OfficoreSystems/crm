<?php

declare(strict_types=1);

namespace Crm\User\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'user_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('@UserModule/security/login.html.twig', [
            // Symfony haelt die letzte Eingabe fest, damit die Adresse nach
            // einem Fehlversuch nicht neu getippt werden muss.
            'lastUsername' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Die Firewall faengt diese Route ab, bevor der Controller laeuft. Der
     * Rumpf wird deshalb nie ausgefuehrt - die Route muss aber existieren,
     * damit Symfony die URL kennt.
     */
    #[Route('/logout', name: 'user_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('Diese Methode wird von der Firewall abgefangen.');
    }
}
