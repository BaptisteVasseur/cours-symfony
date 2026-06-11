<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/logement/{id}/calendrier')]
#[IsGranted('ROLE_HOST')]
final class HostCalendarController extends AbstractController
{
    #[Route('', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function index(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('m'));

        if ($month < 1) {
            $month = 12;
            $year--;
        } elseif ($month > 12) {
            $month = 1;
            $year++;
        }

        $firstDay = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $lastDay = $firstDay->modify('last day of this month');
        $daysInMonth = (int) $lastDay->format('d');

        $blockedEntries = $availabilityRepository->findByPropertyAndMonth($property, $year, $month);
        $blockedDates = [];
        foreach ($blockedEntries as $entry) {
            if (!$entry->isAvailable()) {
                $blockedDates[$entry->getAvailableDate()->format('Y-m-d')] = true;
            }
        }

        $reservations = $reservationRepository->findConfirmedForPropertyAndMonth($property, $firstDay, $lastDay);
        $reservedDates = [];
        foreach ($reservations as $reservation) {
            $current = $reservation->getCheckinDate();
            $end = $reservation->getCheckoutDate();
            while ($current < $end) {
                $reservedDates[$current->format('Y-m-d')] = $reservation;
                $current = $current->modify('+1 day');
            }
        }

        $firstDayOfWeek = (int) $firstDay->format('N');

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'daysInMonth' => $daysInMonth,
            'firstDay' => $firstDay,
            'firstDayOfWeek' => $firstDayOfWeek,
            'blockedDates' => $blockedDates,
            'reservedDates' => $reservedDates,
        ]);
    }

    #[Route('/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function block(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('calendar_block' . $property->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $startStr = $request->getPayload()->getString('date_start');
        $endStr = $request->getPayload()->getString('date_end');

        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startStr);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $endStr);

        if ($start === false || $end === false || $start > $end) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

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
                $entityManager->persist($availability);
            } else {
                $existing->setIsAvailable(false);
            }

            $current = $current->modify('+1 day');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Dates bloquées avec succès.');

        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'year' => (int) $start->format('Y'),
            'month' => (int) $start->format('m'),
        ]);
    }

    #[Route('/debloquer', name: 'app_host_calendar_unblock', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function unblock(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('calendar_unblock' . $property->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $startStr = $request->getPayload()->getString('date_start');
        $endStr = $request->getPayload()->getString('date_end');

        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startStr);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $endStr);

        if ($start === false || $end === false || $start > $end) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $deleted = $availabilityRepository->deleteByPropertyAndRange($property, $start, $end);
        $this->addFlash('success', sprintf('%d jour(s) débloqué(s).', $deleted));

        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'year' => (int) $start->format('Y'),
            'month' => (int) $start->format('m'),
        ]);
    }
}
