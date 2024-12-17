<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Form\PasswordResetType;
use App\Repository\UserRepository;
use App\Service\Mailer\AuthMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ResetPasswordController extends AbstractController
{
    // Forgot password : pour faire la demande de lien
    // Reset password : La page sur laquelle on arrive quand on clique sur le lien avec le formulaire

    #[Route(path: '/forgot', name: 'page_forgot')]
    public function forgot(
        Request $request,
        UserRepository $userRepository,
        AuthMailer $authMailer,
        EntityManagerInterface $entityManager,
    ): Response {
        $email = $request->get('email');
        if ($email) {
            $user = $userRepository->findOneBy(['email' => $email]);
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
}
