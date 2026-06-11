<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/logements/{id}/disponibilites')]
#[IsGranted('ROLE_USER')]
final class HostAvailabilityController extends AbstractController
{
    #[Route('', name: 'app_host_availability', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $host,
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $this->assertOwns($property, $host);

        $month = (string) $request->query->get('month', '');
        $first = \DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01') ?: new \DateTimeImmutable('first day of this month');
        $first = $first->setTime(0, 0);
        $last = $first->modify('last day of this month');

        $blocked = [];
        foreach ($availabilityRepository->findBlockedDates($property, $first, $last) as $pa) {
            $blocked[$pa->getAvailableDate()->format('Y-m-d')] = true;
        }

        $booked = [];
        foreach ($reservationRepository->findConfirmedForProperty($property) as $res) {
            $day = $res->getCheckinDate();
            while ($day < $res->getCheckoutDate()) {
                $booked[$day->format('Y-m-d')] = true;
                $day = $day->modify('+1 day');
            }
        }

        return $this->render('front/host/availability.html.twig', [
            'property' => $property,
            'weeks' => $this->buildWeeks($first, $blocked, $booked),
            'monthLabel' => $first->format('m/Y'),
            'prevMonth' => $first->modify('-1 month')->format('Y-m'),
            'nextMonth' => $first->modify('+1 month')->format('Y-m'),
        ]);
    }

    #[Route('/bloquer', name: 'app_host_availability_block', methods: ['POST'])]
    public function block(#[CurrentUser] User $host, Property $property, Request $request, AvailabilityManager $availabilityManager): Response
    {
        return $this->handleRange($host, $property, $request, $availabilityManager, true);
    }

    #[Route('/debloquer', name: 'app_host_availability_unblock', methods: ['POST'])]
    public function unblock(#[CurrentUser] User $host, Property $property, Request $request, AvailabilityManager $availabilityManager): Response
    {
        return $this->handleRange($host, $property, $request, $availabilityManager, false);
    }

    private function handleRange(User $host, Property $property, Request $request, AvailabilityManager $manager, bool $block): Response
    {
        $this->assertOwns($property, $host);

        if (!$this->isCsrfTokenValid('availability' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('start'));
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('end'));

        if ($start === false || $end === false || $start > $end) {
            $this->addFlash('error', 'Plage de dates invalide.');
        } else {
            $block ? $manager->block($property, $start, $end) : $manager->unblock($property, $start, $end);
            $this->addFlash('success', $block ? 'Période bloquée.' : 'Période débloquée.');
        }

        return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
    }

    /**
     * @param array<string, true> $blocked
     * @param array<string, true> $booked
     * @return list<list<array{day:int,date:string,state:string}|null>>
     */
    private function buildWeeks(\DateTimeImmutable $first, array $blocked, array $booked): array
    {
        $today = new \DateTimeImmutable('today');
        $daysInMonth = (int) $first->format('t');
        $startWeekday = (int) $first->format('N'); // 1 = lundi

        $weeks = [];
        $week = array_fill(0, $startWeekday - 1, null);

        for ($d = 0; $d < $daysInMonth; $d++) {
            $date = $first->modify('+' . $d . ' days');
            $key = $date->format('Y-m-d');

            $state = 'available';
            if (isset($booked[$key])) {
                $state = 'booked';
            } elseif (isset($blocked[$key])) {
                $state = 'blocked';
            }
            if ($date < $today) {
                $state = 'past';
            }

            $week[] = ['day' => $d + 1, 'date' => $key, 'state' => $state];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if ($week !== []) {
            while (count($week) < 7) {
                $week[] = null;
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    private function assertOwns(Property $property, User $host): void
    {
        if ($property->getHost()?->getId() !== $host->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}