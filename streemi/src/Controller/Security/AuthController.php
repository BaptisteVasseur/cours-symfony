<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Form\PasswordResetType;
use App\Repository\UserRepository;
use App\Service\Mailer\AuthMailer;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Uid\Uuid;

class AuthController extends AbstractController
{
    #[Route(path: '/login', name: 'page_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'error' => $error,
            'lastUsername' => $lastUsername,
        ]);
    }

    #[Route(path: '/logout', name: 'page_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/forgot', name: 'page_forgot')]
    public function forgot(
        Request $request,
        UserRepository $userRepository,
        AuthMailer $authMailer,
        EntityManagerInterface $entityManager,
    ): Response {
        $username = $request->get('toto');
        if ($username) {
            $user = $userRepository->findOneBy(['email' => $username]);
            if (!$user) {
                $this->addFlash('error', 'Aucun utilisateur trouvé avec cet email');
            } else {
                $resetPasswordToken = Uuid::v4()->toRfc4122();
                $user->setResetPasswordToken($resetPasswordToken);
                $entityManager->flush();
                $authMailer->sendForgotEmail($user);

                $this->addFlash('success', 'Email envoyé !');
            }
        }

        return $this->render(view: 'auth/forgot.html.twig');
    }

    #[Route(path: '/reset/{uid}', name: 'page_reset')]
    public function reset(
        string $uid,
        UserRepository $userRepository,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $user = $userRepository->findOneBy(['resetPasswordToken' => $uid]);

        if (!$user) {
            $this->addFlash('error', 'Token de réinitialisation invalide');
            return $this->redirectToRoute('page_forgot');
        }

        $form = $this->createForm(PasswordResetType::class);
        $form->setData(['email' => $user->getEmail()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $plainPassword = $data['plainPassword'];
            $repeatPassword = $data['repeatPassword'];

            if ($plainPassword === $repeatPassword) {
                $user->setResetPasswordToken(null);
                $user->setPlainPassword($plainPassword);
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe réinitialisé');
                return $this->redirectToRoute('page_login');
            }

            $this->addFlash('error', 'Les mots de passe ne correspondent pas');
        }

        return $this->render(view: 'auth/reset.html.twig', parameters: [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/register', name: 'register')]
    public function register(): Response
    {
        return $this->render('auth/register.html.twig');
    }
    #[Route(path: '/confirm', name: 'confirm')]
    public function confirm(): Response
    {
        return $this->render('auth/confirm.html.twig');
    }
}
