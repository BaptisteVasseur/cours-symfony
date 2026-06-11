<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * @deprecated Use App\Controller\Front\Auth\LoginController and RegisterController instead.
 * Routes renamed to avoid conflicts.
 */
class SecurityController extends AbstractController
{
    #[Route('/legacy/login', name: 'legacy_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->redirectToRoute('app_login');
    }

    #[Route('/legacy/register', name: 'legacy_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        return $this->redirectToRoute('app_register');
    }

    #[Route('/legacy/logout', name: 'legacy_logout')]
    public function logout(): void
    {
        // Redirected
    }
}
