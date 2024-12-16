<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'register')]
    public function register(): Response
    {
        return $this->render('auth/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): Response
    {
        return new Response('Logout');
    }

    #[Route('/forgot', name: 'forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        MailerInterface $mailer
    ): Response
    {
        $email = $request->get('email');

        if ($email) {
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                $email = (new Email())
                    ->from('contact@streemi.com')
                    ->to($user->getEmail())
                    ->subject('Reset your password')
                    ->text('Click here to reset your password')
                    ->html('<a href="http://localhost:8000/reset?token=toto">Click here to reset your password</a>');

                $mailer->send($email);
            }
        }

        return $this->render('auth/forgot.html.twig');
    }

    #[Route('/confirm', name: 'confirm_account')]
    public function confirm(): Response
    {
        return $this->render('auth/confirm.html.twig');
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return $this->render('auth/index.html.twig');
    }

}
