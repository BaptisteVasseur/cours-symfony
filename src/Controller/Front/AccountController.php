<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\AccountProfileType;
use App\Form\AccountSettingsType;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        ]);
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
        ]);
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

    #[Route('/demandes', name: 'app_account_host_reservations', methods: ['GET'])]
    public function hostReservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/account/host_reservations.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/demandes/{id}/confirmer', name: 'app_account_host_confirm', methods: ['POST'])]
    public function confirmReservation(
        Reservation $reservation,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(ReservationVoter::MANAGE, $reservation);

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être modifiée.');

            return $this->redirectToRoute('app_account_host_reservations');
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('confirmed');
        $history->setChangedBy($this->getUser());

        $reservation->setStatus('confirmed');
        $em->persist($history);
        $em->flush();

        $this->addFlash('success', 'Réservation confirmée.');

        return $this->redirectToRoute('app_account_host_reservations');
    }

    #[Route('/demandes/{id}/refuser', name: 'app_account_host_reject', methods: ['POST'])]
    public function rejectReservation(
        Reservation $reservation,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(ReservationVoter::MANAGE, $reservation);

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation ne peut plus être modifiée.');

            return $this->redirectToRoute('app_account_host_reservations');
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('cancelled');
        $history->setChangedBy($this->getUser());

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason('Demande refusée par l\'hôte.');
        $em->persist($history);
        $em->flush();

        $this->addFlash('info', 'Réservation refusée.');

        return $this->redirectToRoute('app_account_host_reservations');
    }
}
