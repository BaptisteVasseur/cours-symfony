<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_USER')]
final class HostController extends AbstractController
{
    #[Route('', name: 'app_host_dashboard', methods: ['GET'])]
    public function dashboard(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/dashboard.html.twig', [
            'pending' => $reservationRepository->findPendingForHost($user),
            'all'     => $reservationRepository->findByHostForListing($user),
        ]);
    }

    #[Route('/reservation/{id}/accept', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function accept(
        Request $request,
        Reservation $reservation,
        ReservationService $reservationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('accept' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette demande ne peut plus être acceptée.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        $reservationService->confirmReservation($reservation, $user);
        $this->addFlash('success', 'Réservation acceptée.');

        return $this->redirectToRoute('app_host_dashboard');
    }

    #[Route('/reservation/{id}/refuse', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuse(
        Request $request,
        Reservation $reservation,
        ReservationService $reservationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('refuse' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        $reason = trim($request->getPayload()->getString('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        $reservationService->cancelReservation($reservation, $user, $reason);
        $this->addFlash('success', 'Demande refusée.');

        return $this->redirectToRoute('app_host_dashboard');
    }

    #[Route('/property/{id}/availability', name: 'app_host_availability', methods: ['GET'])]
    public function availability(
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $from = new \DateTimeImmutable('first day of this month');
        $to   = new \DateTimeImmutable('last day of next month');

        $availabilities = $availabilityRepository->findForRange(
            (string) $property->getId(),
            $from,
            $to,
        );

        $blockedDates = [];
        foreach ($availabilities as $a) {
            if (!$a->isAvailable()) {
                $blockedDates[] = $a->getAvailableDate()->format('Y-m-d');
            }
        }

        return $this->render('front/host/availability.html.twig', [
            'property'     => $property,
            'blockedDates' => $blockedDates,
            'from'         => $from,
            'to'           => $to,
        ]);
    }

    #[Route('/property/{id}/availability/toggle', name: 'app_host_availability_toggle', methods: ['POST'])]
    public function toggleDay(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('toggle' . $property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
        }

        $dateStr = $request->getPayload()->getString('date');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if ($date === false) {
            $this->addFlash('error', 'Date invalide.');

            return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
        }

        $existing = $availabilityRepository->findForRange(
            (string) $property->getId(),
            $date,
            $date,
        );

        if ($existing !== []) {
            $avail = $existing[0];
            $avail->setIsAvailable(!$avail->isAvailable());
        } else {
            $avail = new PropertyAvailability();
            $avail->setProperty($property);
            $avail->setAvailableDate($date);
            $avail->setIsAvailable(false);
            $em->persist($avail);
        }

        $em->flush();
        $this->addFlash('success', 'Disponibilité mise à jour.');

        return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
    }

    #[Route('/property/{id}/ical-token/generate', name: 'app_host_ical_generate', methods: ['POST'])]
    public function generateIcalToken(
        Request $request,
        Property $property,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('ical' . $property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
        }

        $property->generateIcalToken();
        $em->flush();
        $this->addFlash('success', 'Token iCal généré.');

        return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
    }
}
