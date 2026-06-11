<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Use App\Controller\Admin\UserController instead.
 */
class UserController extends AbstractController
{
    #[Route('/legacy/users', name: 'legacy_user_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_user_index');
    }
}
