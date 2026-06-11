<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\AccountProfileType;
use App\Form\AccountSettingsType;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Message\ReservationCancelledMessage;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        MessageBusInterface $bus,
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

        $bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId()));

        $this->addFlash('info', 'Réservation refusée.');

        return $this->redirectToRoute('app_account_host_reservations');
    }

    #[Route('/proprietes/{id}/calendrier', name: 'app_account_property_calendar', methods: ['GET'])]
    public function propertyCalendar(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $year  = (int) $request->query->get('year',  date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        // Clamp month
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        $blocks       = $availabilityRepository->findForPropertyMonth($property, $year, $month);
        $blockedDates = [];
        foreach ($blocks as $block) {
            if (!$block->isAvailable()) {
                $blockedDates[$block->getAvailableDate()->format('Y-m-d')] = $block;
            }
        }

        // Also show confirmed reservations on the calendar
        $bookedRanges = $reservationRepository->getBookedRanges($property);

        return $this->render('front/account/property_calendar.html.twig', [
            'property'     => $property,
            'year'         => $year,
            'month'        => $month,
            'blockedDates' => $blockedDates,
            'bookedRanges' => $bookedRanges,
        ]);
    }

    #[Route('/proprietes/{id}/calendrier/bloquer', name: 'app_account_property_block_date', methods: ['POST'])]
    public function blockDate(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $dateStr = $request->request->get('date');
        if (!$dateStr) {
            $this->addFlash('error', 'Date invalide.');
            return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if (!$date) {
            $this->addFlash('error', 'Format de date invalide.');
            return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
        }

        // Check if already exists
        $existing = $availabilityRepository->findOneBy(['property' => $property, 'availableDate' => $date]);
        if ($existing) {
            $existing->setIsAvailable(false);
        } else {
            $avail = new \App\Entity\PropertyAvailability();
            $avail->setProperty($property);
            $avail->setAvailableDate($date);
            $avail->setIsAvailable(false);
            $em->persist($avail);
        }

        $em->flush();
        $this->addFlash('success', 'Date bloquée.');

        $year  = $request->request->get('year',  $date->format('Y'));
        $month = $request->request->get('month', $date->format('n'));
        return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId(), 'year' => $year, 'month' => $month]);
    }

    #[Route('/proprietes/{id}/calendrier/debloquer', name: 'app_account_property_unblock_date', methods: ['POST'])]
    public function unblockDate(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $dateStr = $request->request->get('date');
        $date    = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr ?: '');
        if (!$date) {
            return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId()]);
        }

        $existing = $availabilityRepository->findOneBy(['property' => $property, 'availableDate' => $date]);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
            $this->addFlash('success', 'Date débloquée.');
        }

        $year  = $request->request->get('year',  $date->format('Y'));
        $month = $request->request->get('month', $date->format('n'));
        return $this->redirectToRoute('app_account_property_calendar', ['id' => $property->getId(), 'year' => $year, 'month' => $month]);
    }

    #[Route('/proprietes/{id}/ical-token/regenerer', name: 'app_account_property_ical_regenerate', methods: ['POST'])]
    public function regenerateIcalToken(
        Property $property,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas l\'hôte de cette propriété.');
        }

        $property->regenerateIcalToken();
        $em->flush();

        $icalUrl = $this->generateUrl(
            'app_api_property_ical',
            ['id' => $property->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ) . '?token=' . $property->getIcalToken();

        $this->addFlash('success', 'Lien iCal régénéré. Nouveau lien : ' . $icalUrl);

        return $this->redirectToRoute('app_account_properties');
    }
}
