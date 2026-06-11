<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BlockDatesType;
use App\Form\RejectType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/hote')]
#[IsGranted('ROLE_HOST')]
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
            'pendingReservations' => $reservationRepository->findPendingForHost($user),
        ]);
    }

    #[Route('/reservations/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function accept(Request $request, Reservation $reservation, ReservationManager $reservationManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('accept' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_dashboard');
        }

        try {
            $reservationManager->confirm($reservation, $user);
            $this->addFlash('success', 'La demande a été acceptée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_dashboard');
    }

    #[Route('/reservations/{id}/refuser', name: 'app_host_reservation_reject', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function reject(Request $request, Reservation $reservation, ReservationManager $reservationManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(RejectType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reservationManager->reject($reservation, $user, (string) $form->get('reason')->getData());
                $this->addFlash('success', 'La demande a été refusée.');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        } else {
            $this->addFlash('error', 'Le motif du refus est obligatoire.');
        }

        return $this->redirectToRoute('app_host_dashboard');
    }

    #[Route('/logement/{id}/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function calendar(
        Request $request,
        Property $property,
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $month = $this->parseMonth($request->query->get('month'));
        $monthStart = $month;
        $monthEnd = $month->modify('first day of next month');

        $confirmed = $reservationRepository->findConfirmedOverlapping($property, $monthStart, $monthEnd);
        $bookedDays = $this->bookedDays($confirmed, $monthStart, $monthEnd);

        $blockedDays = [];
        $priceOverrides = [];
        foreach ($availabilityRepository->findInRange($property, $monthStart, $monthEnd) as $availability) {
            $key = $availability->getAvailableDate()->format('Y-m-d');
            if (!$availability->isAvailable()) {
                $blockedDays[$key] = true;
            }
            if ($availability->getPriceOverride() !== null) {
                $priceOverrides[$key] = $availability->getPriceOverride();
            }
        }

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'month' => $monthStart,
            'prevMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'leadingBlanks' => (int) $monthStart->format('N') - 1,
            'daysInMonth' => (int) $monthStart->format('t'),
            'bookedDays' => $bookedDays,
            'blockedDays' => $blockedDays,
            'priceOverrides' => $priceOverrides,
            'blockForm' => $this->createForm(BlockDatesType::class, null, [
                'action' => $this->generateUrl('app_host_calendar_block', ['id' => $property->getId()]),
            ])->createView(),
        ]);
    }

    #[Route('/logement/{id}/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function block(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(BlockDatesType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $form->get('startDate')->getData();
            $end = $form->get('endDate')->getData();

            if ($start > $end) {
                $this->addFlash('error', 'La date de début doit précéder la date de fin.');

                return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
            }

            $this->blockRange($property, $start, $end, $availabilityRepository, $entityManager);
            $entityManager->flush();
            $this->addFlash('success', 'Période d\'indisponibilité enregistrée.');
        } else {
            $this->addFlash('error', 'Veuillez renseigner une période valide.');
        }

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/logement/{id}/token-ical', name: 'app_host_calendar_token', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function regenerateToken(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('ical-token' . $property->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $property->regenerateCalendarToken();
        $entityManager->flush();
        $this->addFlash('success', 'Nouveau lien de synchronisation iCal généré.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    private function blockRange(
        Property $property,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): void {
        $start = $start->setTime(0, 0);
        $rangeEnd = $end->setTime(0, 0)->modify('+1 day');

        $existing = [];
        foreach ($availabilityRepository->findInRange($property, $start, $rangeEnd) as $availability) {
            $existing[$availability->getAvailableDate()->format('Y-m-d')] = $availability;
        }

        for ($day = $start; $day < $rangeEnd; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $availability = $existing[$key] ?? null;
            if ($availability === null) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($day);
                $property->addAvailability($availability);
                $entityManager->persist($availability);
            }
            $availability->setIsAvailable(false);
        }
    }

    private function bookedDays(array $reservations, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = [];
        foreach ($reservations as $reservation) {
            $cursor = $reservation->getCheckinDate()->setTime(0, 0);
            $checkout = $reservation->getCheckoutDate()->setTime(0, 0);
            while ($cursor < $checkout) {
                if ($cursor >= $from && $cursor < $to) {
                    $days[$cursor->format('Y-m-d')] = true;
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $days;
    }

    private function parseMonth(?string $value): \DateTimeImmutable
    {
        if ($value !== null && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value . '-01');
            if ($date !== false) {
                return $date->setTime(0, 0);
            }
        }

        return (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
    }
}
