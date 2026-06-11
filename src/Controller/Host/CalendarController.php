<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Enum\ReservationStatus;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_HOST')]
final class CalendarController extends AbstractController
{
    #[Route('/hote/logements/{id}/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function index(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $month = $this->resolveMonth($request->query->get('mois'));
        $monthEnd = $month->modify('first day of next month');

        $blocked = [];
        foreach ($availabilityRepository->findInRange($property, $month, $monthEnd) as $availability) {
            if (!$availability->isAvailable()) {
                $blocked[$availability->getAvailableDate()->format('Y-m-d')] = true;
            }
        }

        $booked = [];
        foreach ($reservationRepository->findConflicting($property, $month, $monthEnd, [ReservationStatus::Confirmed->value]) as $reservation) {
            for ($day = $reservation->getCheckinDate(); $day < $reservation->getCheckoutDate(); $day = $day->modify('+1 day')) {
                $booked[$day->format('Y-m-d')] = true;
            }
        }

        $today = new \DateTimeImmutable('today');
        $days = [];
        $daysInMonth = (int) $month->format('t');
        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $date = $month->modify(sprintf('+%d days', $d - 1));
            $key = $date->format('Y-m-d');
            $days[] = [
                'day' => $d,
                'date' => $key,
                'state' => match (true) {
                    isset($booked[$key]) => 'booked',
                    isset($blocked[$key]) => 'blocked',
                    $date < $today => 'past',
                    default => 'available',
                },
            ];
        }

        return $this->render('host/calendar/index.html.twig', [
            'property' => $property,
            'month' => $month,
            'leadingBlanks' => (int) $month->format('N') - 1,
            'days' => $days,
            'prevMonth' => $month->modify('-1 month')->format('Y-m'),
            'nextMonth' => $month->modify('+1 month')->format('Y-m'),
        ]);
    }

    #[Route('/hote/logements/{id}/calendrier/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function block(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('host_calendar_' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $from = $this->parseDate($request->request->get('from'));
        $to = $this->parseDate($request->request->get('to'));
        $makeAvailable = $request->request->get('action') === 'unblock';

        if ($from === null || $to === null || $from >= $to) {
            $this->addFlash('error', 'Veuillez sélectionner une plage de dates valide.');

            return $this->redirectToCalendar($property, $from);
        }

        $existing = [];
        foreach ($availabilityRepository->findInRange($property, $from, $to) as $availability) {
            $existing[$availability->getAvailableDate()->format('Y-m-d')] = $availability;
        }

        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            $availability = $existing[$day->format('Y-m-d')] ?? null;
            if ($availability === null) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($day);
                $entityManager->persist($availability);
            }
            $availability->setIsAvailable($makeAvailable);
        }

        $entityManager->flush();

        $this->addFlash('success', $makeAvailable
            ? 'Les dates ont été rouvertes à la réservation.'
            : 'Les dates ont été bloquées.');

        return $this->redirectToCalendar($property, $from);
    }

    #[Route('/hote/logements/{id}/calendrier/ical', name: 'app_host_calendar_token', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function regenerateToken(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('host_calendar_token_' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $property->setExportToken(bin2hex(random_bytes(32)));
        $entityManager->flush();

        $this->addFlash('success', 'Le lien de synchronisation iCal a été généré.');

        return $this->redirectToCalendar($property, null);
    }

    private function redirectToCalendar(Property $property, ?\DateTimeImmutable $month): Response
    {
        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'mois' => ($month ?? new \DateTimeImmutable())->format('Y-m'),
        ]);
    }

    private function resolveMonth(mixed $value): \DateTimeImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $month = \DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01');
            if ($month !== false) {
                return $month;
            }
        }

        return new \DateTimeImmutable('first day of this month 00:00');
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }
}
