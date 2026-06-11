<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Service\CalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/calendar')]
#[IsGranted('ROLE_HOST')]
final class HostCalendarController extends AbstractController
{
    /**
     * Calendrier global : toutes les réservations et nuits bloquées de l'hôte, en une vue (?month=YYYY-MM).
     */
    #[Route('', name: 'app_host_calendar', methods: ['GET'])]
    public function index(
        Request $request,
        CalendarService $calendarService,
        PropertyRepository $propertyRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        [$year, $month] = $this->resolveMonth($request->query->get('month'));
        $current = (new \DateTimeImmutable())->setDate($year, $month, 1);

        return $this->render('host/calendar/index.html.twig', [
            'calendar' => $calendarService->buildGlobalMonthView($user, $year, $month),
            'properties' => $propertyRepository->findBy(['host' => $user], ['createdAt' => 'DESC']),
            'prevMonth' => $current->modify('-1 month')->format('Y-m'),
            'nextMonth' => $current->modify('+1 month')->format('Y-m'),
        ]);
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function resolveMonth(?string $value): array
    {
        if ($value !== null && preg_match('/^(\d{4})-(\d{1,2})$/', $value, $m) === 1) {
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return [(int) $m[1], $month];
            }
        }

        $now = new \DateTimeImmutable();

        return [(int) $now->format('Y'), (int) $now->format('n')];
    }
}
