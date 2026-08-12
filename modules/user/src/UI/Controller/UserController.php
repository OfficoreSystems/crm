<?php

declare(strict_types=1);

namespace Crm\User\UI\Controller;

use Crm\User\Domain\Role;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', name: 'user_')]
#[IsGranted(Role::ADMIN->value)]
final class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@UserModule/user/index.html.twig');
    }
}
