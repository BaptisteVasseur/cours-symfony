<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccountConfirmationController extends AbstractController
{
    #[Route('/account-confirmation/{token}', name: 'account_confirmation')]
    public function index(?string $token, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['activationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Invalid activation token.');
            return $this->redirectToRoute('app_home');
        }

        $user->setState('active');
        $user->setActivationToken(null);
        $entityManager->flush();

        return $this->render('security/account_confirmation.html.twig');
    }
}
