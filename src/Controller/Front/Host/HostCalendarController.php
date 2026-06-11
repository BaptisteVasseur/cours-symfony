<?php

declare(strict_types=1);

namespace App\Controller\Front\Host;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
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

#[Route('/hote/logements/{id}/calendrier')]
#[IsGranted('ROLE_HOST')]
final class HostCalendarController extends AbstractController
{
    #[Route('', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function index(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        $blockedDates = $availabilityService->getBlockedDatesForMonth($property, $year, $month);

        $firstDay = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = $firstDay->modify('last day of this month');
        $prevMonth = $firstDay->modify('-1 month');
        $nextMonth = $firstDay->modify('+1 month');

        return $this->render('front/host/calendar/index.html.twig', [
            'property' => $property,
            'blockedDates' => $blockedDates,
            'year' => $year,
            'month' => $month,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

    #[Route('/toggle', name: 'app_host_calendar_toggle', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function toggleDate(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        if (!$this->isCsrfTokenValid('calendar_toggle', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $dateStr = $request->request->get('date', '');
        try {
            $date = new \DateTimeImmutable($dateStr);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Date invalide'], 400);
        }

        $existing = $availabilityRepository->findOneBy([
            'property' => $property,
            'availableDate' => $date,
        ]);

        if ($existing !== null) {
            $existing->setIsAvailable(!$existing->isAvailable());
            $entityManager->flush();
            $blocked = !$existing->isAvailable();
        } else {
            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setAvailableDate($date);
            $availability->setIsAvailable(false);
            $entityManager->persist($availability);
            $entityManager->flush();
            $blocked = true;
        }

        return new JsonResponse(['date' => $dateStr, 'blocked' => $blocked]);
    }
}
