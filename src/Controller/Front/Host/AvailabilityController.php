<?php

declare(strict_types=1);

namespace App\Controller\Front\Host;

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

#[Route('/hote/proprietes/{id}')]
#[IsGranted('ROLE_USER')]
#[IsGranted(PropertyVoter::EDIT, subject: 'property')]
final class AvailabilityController extends AbstractController
{
    #[Route('/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $year  = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $blockedDates = [];
        foreach ($availabilityRepository->findByPropertyAndMonth($property, $year, $month) as $pa) {
            if (!$pa->isAvailable()) {
                $blockedDates[$pa->getAvailableDate()->format('Y-m-d')] = true;
            }
        }

        $confirmedDates = [];
        $firstDay = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $lastDay  = $firstDay->modify('last day of this month');

        foreach ($reservationRepository->findConfirmedForPeriod($property, $firstDay, $lastDay->modify('+1 day')) as $reservation) {
            $cursor = $reservation->getCheckinDate();
            while ($cursor < $reservation->getCheckoutDate()) {
                $confirmedDates[$cursor->format('Y-m-d')] = true;
                $cursor = $cursor->modify('+1 day');
            }
        }

        $daysInMonth  = (int) $firstDay->format('t');
        $startWeekday = (int) $firstDay->format('N');

        return $this->render('front/host/calendar.html.twig', [
            'property'       => $property,
            'year'           => $year,
            'month'          => $month,
            'daysInMonth'    => $daysInMonth,
            'startWeekday'   => $startWeekday,
            'blockedDates'   => $blockedDates,
            'confirmedDates' => $confirmedDates,
        ]);
    }

    #[Route('/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    public function block(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('calendar_block_'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToCalendar($property, $request);
        }

        $startStr = $request->request->get('date_start');
        $endStr   = $request->request->get('date_end') ?: $startStr;

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Exception) {
            $this->addFlash('error', 'Dates invalides.');
            return $this->redirectToCalendar($property, $request);
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $existing = [];
        foreach ($availabilityRepository->findByPropertyAndMonth($property, (int)$start->format('Y'), (int)$start->format('n')) as $pa) {
            $existing[$pa->getAvailableDate()->format('Y-m-d')] = $pa;
        }

        $cursor = $start;
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            if (isset($existing[$key])) {
                $existing[$key]->setIsAvailable(false);
            } else {
                $pa = new PropertyAvailability();
                $pa->setProperty($property);
                $pa->setAvailableDate($cursor);
                $pa->setIsAvailable(false);
                $em->persist($pa);
            }
            $cursor = $cursor->modify('+1 day');
        }

        $em->flush();
        $this->addFlash('success', 'Période bloquée.');

        return $this->redirectToCalendar($property, $request);
    }

    #[Route('/debloquer', name: 'app_host_calendar_unblock', methods: ['POST'])]
    public function unblock(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('calendar_block_'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToCalendar($property, $request);
        }

        $dateStr = $request->request->get('date');
        try {
            $date = new \DateTimeImmutable($dateStr);
        } catch (\Exception) {
            $this->addFlash('error', 'Date invalide.');
            return $this->redirectToCalendar($property, $request);
        }

        $entries = $availabilityRepository->findByPropertyAndMonth($property, (int)$date->format('Y'), (int)$date->format('n'));
        foreach ($entries as $pa) {
            if ($pa->getAvailableDate()->format('Y-m-d') === $date->format('Y-m-d') && !$pa->isAvailable()) {
                $em->remove($pa);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Jour débloqué.');

        return $this->redirectToCalendar($property, $request);
    }

    private function redirectToCalendar(Property $property, Request $request): Response
    {
        return $this->redirectToRoute('app_host_calendar', [
            'id'    => $property->getId(),
            'year'  => $request->request->get('year', date('Y')),
            'month' => $request->request->get('month', date('n')),
        ]);
    }
}
