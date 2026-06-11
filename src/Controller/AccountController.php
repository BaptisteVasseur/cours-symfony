<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->getUser();

        \assert($user instanceof User);

        return $this->render('account/dashboard.html.twig', [
            'user' => $user,
        ]);
    }
}
