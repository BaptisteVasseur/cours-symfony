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
    #[Route('/reservations', name: 'app_host_reservations', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'pending' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/reservations/{id}/confirm', name: 'app_host_reservation_confirm', methods: ['POST'])]
    public function confirm(Reservation $reservation, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $reservationService->confirm($reservation, $user);
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/reservations/{id}/refuse', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuse(Reservation $reservation, Request $request, ReservationService $reservationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $reason = $request->request->get('reason', '');
        if (empty($reason)) {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');
            return $this->redirectToRoute('app_host_reservations');
        }

        try {
            $reservationService->cancel($reservation, $user, $reason);
            $this->addFlash('success', 'Demande refusée.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/calendar/{id}', name: 'app_host_calendar', methods: ['GET', 'POST'])]
    public function calendar(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $startStr = $request->request->get('start_date');
            $endStr = $request->request->get('end_date');

            $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startStr);
            $end = \DateTimeImmutable::createFromFormat('Y-m-d', $endStr);

            if ($start && $end && $end >= $start) {
                $current = $start;
                while ($current <= $end) {
                    $existing = $availabilityRepository->findOneBy([
                        'property' => $property,
                        'availableDate' => $current,
                    ]);

                    if ($existing === null) {
                        $availability = new PropertyAvailability();
                        $availability->setProperty($property);
                        $availability->setAvailableDate($current);
                        $availability->setIsAvailable(false);
                        $em->persist($availability);
                    } else {
                        $existing->setIsAvailable(false);
                    }

                    $current = $current->modify('+1 day');
                }
                $em->flush();
                $this->addFlash('success', 'Période bloquée avec succès.');
            } else {
                $this->addFlash('error', 'Dates invalides.');
            }

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $now = new \DateTimeImmutable();
        $endOfMonth = $now->modify('+2 months');
        $blockedDays = $availabilityRepository->findByDateRange($property, $now, $endOfMonth);

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'blockedDays' => $blockedDays,
        ]);
    }
}