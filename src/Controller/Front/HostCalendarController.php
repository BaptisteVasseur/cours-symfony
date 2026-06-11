<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/logement/{id}')]
#[IsGranted('ROLE_USER')]
final class HostCalendarController extends AbstractController
{
    #[Route('/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        $monthParam = $request->query->getString('month');
        try {
            $current = $monthParam
                ? new \DateTimeImmutable($monthParam . '-01')
                : new \DateTimeImmutable('first day of this month');
        } catch (\Exception) {
            $current = new \DateTimeImmutable('first day of this month');
        }

        $year = (int) $current->format('Y');
        $month = (int) $current->format('n');

        $blockedDates = $availabilityRepository->findBlockedDatesForMonth($property, $year, $month);
        $blockedKeys = array_map(fn(\DateTimeImmutable $d) => $d->format('Y-m-d'), $blockedDates);

        $reservations = $reservationRepository->findConfirmedForMonth($property, $year, $month);

        $firstOfMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'firstWeekday' => (int) $firstOfMonth->format('N'), // 1=lundi, 7=dimanche
            'daysInMonth' => (int) $firstOfMonth->modify('last day of this month')->format('j'),
            'blockedDates' => $blockedKeys,
            'reservations' => $reservations,
            'prev' => $current->modify('first day of last month')->format('Y-m'),
            'next' => $current->modify('first day of next month')->format('Y-m'),
        ]);
    }

    #[Route('/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    public function block(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);
        $this->denyAccessUnlessGrantedCsrf($request);

        $startStr = $request->request->getString('start');
        $endStr = $request->request->getString('end');

        try {
            $start = new \DateTimeImmutable($startStr);
            $end = new \DateTimeImmutable($endStr);
        } catch (\Exception) {
            $this->addFlash('error', 'Dates invalides.');
            return $this->redirectToCalendar($property, $startStr);
        }

        if ($end <= $start) {
            $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
            return $this->redirectToCalendar($property, $startStr);
        }

        $current = $start;
        while ($current < $end) {
            $key = $current->format('Y-m-d');
            $existing = $availabilityRepository->findOneBy(['property' => $property, 'blockedDate' => $current]);
            if ($existing === null) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setBlockedDate($current);
                $em->persist($availability);
            }
            $current = $current->modify('+1 day');
        }

        $em->flush();
        $this->addFlash('success', 'Période bloquée.');

        return $this->redirectToCalendar($property, $startStr);
    }

    #[Route('/debloquer/{date}', name: 'app_host_calendar_unblock', methods: ['DELETE', 'POST'])]
    public function unblock(
        Property $property,
        string $date,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('PROPERTY_EDIT', $property);

        try {
            $blockedDate = new \DateTimeImmutable($date);
        } catch (\Exception) {
            $this->addFlash('error', 'Date invalide.');
            return $this->redirectToCalendar($property, $date);
        }

        $availability = $availabilityRepository->findOneBy(['property' => $property, 'blockedDate' => $blockedDate]);
        if ($availability !== null) {
            $em->remove($availability);
            $em->flush();
            $this->addFlash('success', 'Jour débloqué.');
        }

        return $this->redirectToCalendar($property, $date);
    }

    private function denyAccessUnlessGrantedCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('host_calendar', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
    }

    private function redirectToCalendar(Property $property, string $dateHint): Response
    {
        try {
            $month = (new \DateTimeImmutable($dateHint))->format('Y-m');
        } catch (\Exception) {
            $month = (new \DateTimeImmutable())->format('Y-m');
        }

        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'month' => $month,
        ]);
    }
}