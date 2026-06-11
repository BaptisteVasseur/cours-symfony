<?php

declare(strict_types=1);

namespace App\Controller\Front\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EmailVerificationController extends AbstractController
{
    #[Route('/email/verify/{token}', name: 'app_email_verify', methods: ['GET'])]
    public function verify(
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->findOneBy(['emailVerificationToken' => $token]);

        if ($user === null) {
            $this->addFlash('error', 'Lien de vérification invalide ou expiré.');

            return $this->redirectToRoute('app_login');
        }

        $user->setIsEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Votre adresse email a été vérifiée avec succès !');

        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route('/email/resend-verification', name: 'app_email_resend_verification', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resend(
        EntityManagerInterface $entityManager,
        MailService $mailService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->isEmailVerified()) {
            $this->addFlash('success', 'Votre email est déjà vérifié.');

            return $this->redirectToRoute('app_home');
        }

        $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $mailService->sendVerificationEmail($user);

        $this->addFlash('success', 'Un nouvel email de vérification a été envoyé.');

        return $this->redirectToRoute('app_home');
    }
}
