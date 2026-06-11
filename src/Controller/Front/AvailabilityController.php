<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/logement/{id}/disponibilites')]
#[IsGranted('ROLE_USER')]
class AvailabilityController extends AbstractController
{
    #[Route('', name: 'app_availability_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function calendar(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        $year = (int) ($request->query->get('year') ?? (new \DateTimeImmutable())->format('Y'));
        $month = (int) ($request->query->get('month') ?? (new \DateTimeImmutable())->format('n'));

        // Clamp month to 1-12
        if ($month < 1) {
            $month = 12;
            $year--;
        } elseif ($month > 12) {
            $month = 1;
            $year++;
        }

        $calendar = $availabilityService->getMonthCalendar($property, $year, $month);

        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }

        return $this->render('front/availability/calendar.html.twig', [
            'property' => $property,
            'calendar' => $calendar,
            'year' => $year,
            'month' => $month,
            'prevYear' => $prevYear,
            'prevMonth' => $prevMonth,
            'nextYear' => $nextYear,
            'nextMonth' => $nextMonth,
        ]);
    }

    #[Route('/toggle', name: 'app_availability_toggle', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function toggle(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $repository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $dateStr = $request->request->get('date');
        if ($dateStr === null || $dateStr === '') {
            return $this->json(['error' => 'Date manquante.'], 400);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if ($date === false) {
            return $this->json(['error' => 'Format de date invalide.'], 400);
        }
        $date = $date->setTime(0, 0, 0);

        $existing = $repository->findOneByPropertyAndDate($property, $date);

        if ($existing !== null) {
            // Toggle: flip isAvailable
            $existing->setIsAvailable(!$existing->isAvailable());
            $isNowAvailable = $existing->isAvailable();
            if ($isNowAvailable) {
                // If unlocked, remove the row entirely
                $em->remove($existing);
            }
        } else {
            // No row = was available, now block it
            $row = new PropertyAvailability();
            $row->setProperty($property);
            $row->setAvailableDate($date);
            $row->setIsAvailable(false);
            $em->persist($row);
            $isNowAvailable = false;
        }

        $em->flush();

        return $this->json([
            'date' => $dateStr,
            'isAvailable' => $isNowAvailable ?? true,
        ]);
    }

    #[Route('/block-range', name: 'app_availability_block_range', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function blockRange(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $repository,
        EntityManagerInterface $em,
    ): Response {
        $startStr = $request->request->get('start');
        $endStr = $request->request->get('end');
        $action = $request->request->get('action', 'block'); // 'block' or 'unblock'

        if ($startStr === null || $endStr === null) {
            $this->addFlash('error', 'Les dates de début et de fin sont requises.');
            return $this->redirectToRoute('app_availability_calendar', ['id' => $property->getId()]);
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startStr)?->setTime(0, 0, 0);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $endStr)?->setTime(0, 0, 0);

        if ($start === null || $end === null || $start > $end) {
            $this->addFlash('error', 'Plage de dates invalide.');
            return $this->redirectToRoute('app_availability_calendar', ['id' => $property->getId()]);
        }

        if ($action === 'unblock') {
            $repository->deleteBlockedInRange($property, $start, $end->modify('+1 day'));
            $this->addFlash('success', 'Dates débloquées avec succès.');
        } else {
            // Remove existing rows in range first to avoid duplicates
            $repository->deleteBlockedInRange($property, $start, $end->modify('+1 day'));

            $cursor = $start;
            while ($cursor <= $end) {
                $row = new PropertyAvailability();
                $row->setProperty($property);
                $row->setAvailableDate($cursor);
                $row->setIsAvailable(false);
                $em->persist($row);
                $cursor = $cursor->modify('+1 day');
            }
            $em->flush();
            $this->addFlash('success', 'Dates bloquées avec succès.');
        }

        return $this->redirectToRoute('app_availability_calendar', [
            'id' => $property->getId(),
            'year' => $start->format('Y'),
            'month' => (int) $start->format('n'),
        ]);
    }
}
