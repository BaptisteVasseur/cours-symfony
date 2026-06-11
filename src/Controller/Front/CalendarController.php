<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/calendrier')]
#[IsGranted('ROLE_USER')]
final class CalendarController extends AbstractController
{
    #[Route('/{id}', name: 'app_calendar_show', methods: ['GET'])]
    public function show(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        $this->denyAccessUnlessHost($property);

        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('n'));

        // Naviguer entre les mois
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        $occupied = $availabilityService->getOccupiedDatesForMonth($property, $year, $month);

        $daysInMonth = (int) (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month)))->format('t');
        $firstDayOfWeek = (int) (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month)))->format('N');

        return $this->render('front/calendar/show.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'occupied' => $occupied,
            'daysInMonth' => $daysInMonth,
            'firstDayOfWeek' => $firstDayOfWeek,
        ]);
    }

    #[Route('/{id}/bloquer', name: 'app_calendar_block', methods: ['POST'])]
    public function block(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $this->denyAccessUnlessHost($property);
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('from'));
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('to'));

        if (!$from instanceof \DateTimeImmutable || !$to instanceof \DateTimeImmutable || $from > $to) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_calendar_show', ['id' => $property->getId()]);
        }

        $cursor = $from;
        while ($cursor <= $to) {
            $existing = $availabilityRepository->findOneByPropertyAndDate($property, $cursor);
            if ($existing === null) {
                $pa = new PropertyAvailability();
                $pa->setProperty($property);
                $pa->setAvailableDate($cursor);
                $pa->setIsAvailable(false);
                $entityManager->persist($pa);
            } else {
                $existing->setIsAvailable(false);
            }
            $cursor = $cursor->modify('+1 day');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Période bloquée avec succès.');

        return $this->redirectToRoute('app_calendar_show', [
            'id' => $property->getId(),
            'year' => $from->format('Y'),
            'month' => $from->format('n'),
        ]);
    }

    #[Route('/{id}/debloquer', name: 'app_calendar_unblock', methods: ['POST'])]
    public function unblock(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $this->denyAccessUnlessHost($property);

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('from'));
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('to'));

        if (!$from instanceof \DateTimeImmutable || !$to instanceof \DateTimeImmutable || $from > $to) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_calendar_show', ['id' => $property->getId()]);
        }

        $cursor = $from;
        while ($cursor <= $to) {
            $existing = $availabilityRepository->findOneByPropertyAndDate($property, $cursor);
            if ($existing !== null) {
                $entityManager->remove($existing);
            }
            $cursor = $cursor->modify('+1 day');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Période débloquée avec succès.');

        return $this->redirectToRoute('app_calendar_show', [
            'id' => $property->getId(),
            'year' => $from->format('Y'),
            'month' => $from->format('n'),
        ]);
    }

    private function denyAccessUnlessHost(Property $property): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Accès réservé à l\'hôte de ce logement.');
        }
    }
}
