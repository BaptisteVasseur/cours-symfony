<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Form\UnavailabilityType;
use App\Repository\ReservationRepository;
use App\Repository\UnavailabilityRepository;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/proprietes/{id}/calendrier')]
#[IsGranted('ROLE_USER')]
final class HostCalendarController extends AbstractController
{
    #[Route('', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function index(
        Request $request,
        Property $property,
        UnavailabilityRepository $unavailabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $now = new \DateTimeImmutable('today');
        $year = max(2000, min(2100, (int) ($request->query->get('year') ?: $now->format('Y'))));
        $month = max(1, min(12, (int) ($request->query->get('month') ?: $now->format('n'))));

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $monthEnd = $monthStart->modify('+1 month');

        $unavailabilities = $unavailabilityRepository->findForPropertyBetween($property, $monthStart, $monthEnd);
        $reservations = $reservationRepository->findOverlapping($property, $monthStart, $monthEnd, ['confirmed', 'pending']);

        $cells = $this->buildMonthGrid($monthStart, $now, $unavailabilities, $reservations);

        $form = $this->createForm(UnavailabilityType::class, null, [
            'action' => $this->generateUrl('app_host_calendar_block', [
                'id' => (string) $property->getId(),
                'year' => $year,
                'month' => $month,
            ]),
        ])->createView();

        $upcoming = $unavailabilityRepository->findForPropertyBetween(
            $property,
            $now,
            $now->modify('+2 years'),
        );

        return $this->render('front/host/calendar/index.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'monthStart' => $monthStart,
            'cells' => $cells,
            'prevMonth' => $monthStart->modify('-1 month'),
            'nextMonth' => $monthStart->modify('+1 month'),
            'form' => $form,
            'upcomingUnavailabilities' => $upcoming,
        ]);
    }

    #[Route('/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function block(
        Request $request,
        Property $property,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepository,
        UnavailabilityRepository $unavailabilityRepository,
    ): Response {
        $unav = new Unavailability();
        $unav->setProperty($property);

        $form = $this->createForm(UnavailabilityType::class, $unav);
        $form->handleRequest($request);

        $year = (int) $request->query->get('year', (int) (new \DateTimeImmutable())->format('Y'));
        $month = (int) $request->query->get('month', (int) (new \DateTimeImmutable())->format('n'));

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $unav->getStartDate();
            $end = $unav->getEndDate();
            $today = new \DateTimeImmutable('today');

            if ($start < $today) {
                $this->addFlash('error', 'Impossible de bloquer une période dans le passé.');
            } else {
                $reservationConflicts = $reservationRepository->findOverlapping($property, $start, $end, ['confirmed']);
                if ($reservationConflicts !== []) {
                    $this->addFlash('error', 'Impossible de bloquer ces dates : une réservation confirmée chevauche la période.');
                } else {
                    $unavailabilityConflicts = $unavailabilityRepository->findOverlapping($property, $start, $end);
                    if ($unavailabilityConflicts !== []) {
                        $this->addFlash('error', 'Cette période chevauche un blocage existant.');
                    } else {
                        $unav->setSource('manual');
                        $em->persist($unav);
                        $em->flush();
                        $this->addFlash('success', 'Période bloquée avec succès.');
                        $year = (int) $start->format('Y');
                        $month = (int) $start->format('n');
                    }
                }
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_host_calendar', [
            'id' => (string) $property->getId(),
            'year' => $year,
            'month' => $month,
        ]);
    }

    #[Route('/{unavId}/supprimer', name: 'app_host_calendar_unblock', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function unblock(
        Request $request,
        Property $property,
        string $unavId,
        UnavailabilityRepository $unavailabilityRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('unavailability_unblock_'.$unavId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => (string) $property->getId()]);
        }

        $unav = $unavailabilityRepository->find($unavId);
        if (!$unav instanceof Unavailability || $unav->getProperty()?->getId() != $property->getId()) {
            $this->addFlash('error', 'Période introuvable.');

            return $this->redirectToRoute('app_host_calendar', ['id' => (string) $property->getId()]);
        }

        if ($unav->getSource() !== 'manual') {
            $this->addFlash('error', 'Seules les périodes manuelles peuvent être supprimées depuis cette interface.');

            return $this->redirectToRoute('app_host_calendar', ['id' => (string) $property->getId()]);
        }

        $year = (int) $unav->getStartDate()?->format('Y') ?: (int) date('Y');
        $month = (int) $unav->getStartDate()?->format('n') ?: (int) date('n');

        $em->remove($unav);
        $em->flush();
        $this->addFlash('success', 'Période débloquée.');

        return $this->redirectToRoute('app_host_calendar', [
            'id' => (string) $property->getId(),
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * @param list<Unavailability> $unavailabilities
     * @param list<\App\Entity\Reservation> $reservations
     *
     * @return list<array<string, mixed>>
     */
    private function buildMonthGrid(
        \DateTimeImmutable $monthStart,
        \DateTimeImmutable $today,
        array $unavailabilities,
        array $reservations,
    ): array {
        $firstWeekday = (int) $monthStart->format('N');
        $daysInMonth = (int) $monthStart->format('t');

        $cells = [];
        for ($i = 1; $i < $firstWeekday; $i++) {
            $cells[] = ['type' => 'empty'];
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $monthStart->modify(sprintf('+%d day', $d - 1));

            $blocking = null;
            foreach ($unavailabilities as $u) {
                if ($u->getStartDate() <= $date && $u->getEndDate() > $date) {
                    $blocking = $u;
                    break;
                }
            }

            $reservation = null;
            foreach ($reservations as $r) {
                if ($r->getCheckinDate() <= $date && $r->getCheckoutDate() > $date) {
                    $reservation = $r;
                    break;
                }
            }

            $cells[] = [
                'type' => 'day',
                'day' => $d,
                'date' => $date,
                'isPast' => $date < $today,
                'isToday' => $date->format('Y-m-d') === $today->format('Y-m-d'),
                'blocking' => $blocking,
                'reservation' => $reservation,
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = ['type' => 'empty'];
        }

        return $cells;
    }
}
