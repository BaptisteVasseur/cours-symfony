<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\AccountProfileType;
use App\Form\AccountSettingsType;
use App\Repository\PropertyRepository;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/profil', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getProfile() === null) {
            $user->setProfile(new UserProfile());
        }

        $form = $this->createForm(AccountProfileType::class, $user->getProfile());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('front/account/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ], new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK
        ));
    }

    #[Route('/parametres', name: 'app_account_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(AccountSettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Paramètres enregistrés.');

            return $this->redirectToRoute('app_account_settings');
        }

        return $this->render('front/account/settings.html.twig', [
            'user' => $user,
            'form' => $form,
        ], new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK
        ));
    }

    #[Route('/desactiver-hote', name: 'app_account_deactivate_host', methods: ['POST'])]
    public function deactivateHost(
        Request $request,
        EntityManagerInterface $entityManager,
        PropertyRepository $propertyRepository,
        Security $security,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('deactivate_host', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_account_settings');
        }

        if (!in_array(Roles::HOST, $user->getRoles(), true)) {
            $this->addFlash('error', 'Vous n\'êtes pas hôte.');
            return $this->redirectToRoute('app_account_settings');
        }

        $properties = $propertyRepository->findBy(['host' => $user]);
        foreach ($properties as $property) {
            foreach ($property->getReviews() as $review) {
                foreach ($review->getReports() as $report) {
                    $entityManager->remove($report);
                }
                foreach ($review->getMedia() as $media) {
                    $entityManager->remove($media);
                }
                $entityManager->remove($review);
            }

            foreach ($property->getReservations() as $reservation) {
                foreach ($reservation->getStatusHistory() as $history) {
                    $entityManager->remove($history);
                }
                foreach ($reservation->getPayouts() as $payout) {
                    $entityManager->remove($payout);
                }
                foreach ($reservation->getDisputes() as $dispute) {
                    $entityManager->remove($dispute);
                }
                foreach ($reservation->getConversations() as $conversation) {
                    $entityManager->remove($conversation);
                }
                foreach ($reservation->getPayments() as $payment) {
                    $entityManager->remove($payment);
                }
                $entityManager->remove($reservation);
            }

            $entityManager->remove($property);
        }

        $user->removeAssignedRole(Roles::HOST);
        $entityManager->flush();

        $security->login($user);

        $this->addFlash('success', 'Votre statut d\'hôte a été désactivé et toutes vos annonces ont été supprimées.');

        return $this->redirectToRoute('app_account_settings');
    }

    #[Route('/proprietes', name: 'app_account_properties', methods: ['GET'])]
    public function properties(PropertyRepository $propertyRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/account/properties.html.twig', [
            'properties' => $propertyRepository->findByHost($user),
        ]);
    }
}
