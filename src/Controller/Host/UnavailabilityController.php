<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use App\Form\UnavailabilityType;
use App\Repository\PropertyUnavailabilityRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/logements/{id}/disponibilites')]
#[IsGranted('ROLE_USER')]
final class UnavailabilityController extends AbstractController
{
    #[Route('', name: 'app_host_availability_index', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function index(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
        ReservationRepository $reservationRepository,
        PropertyUnavailabilityRepository $unavailabilityRepository,
    ): Response {
        $unavailability = new PropertyUnavailability();
        $unavailability->setProperty($property);

        $form = $this->createForm(UnavailabilityType::class, $unavailability);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($unavailability);
            $entityManager->flush();
            $this->addFlash('success', 'Période d\'indisponibilité enregistrée.');

            return $this->redirectToRoute('app_host_availability_index', [
                'id' => $property->getId(),
                'month' => $request->query->get('month'),
            ]);
        }

        $month = $this->resolveMonth($request->query->get('month'));
        $monthEnd = $month->modify('first day of next month');

        $reservations = $reservationRepository->findConfirmedOverlapping($property, $month, $monthEnd);
        $unavailabilities = $unavailabilityRepository->findOverlapping($property, $month, $monthEnd);

        return $this->render('host/availability/index.html.twig', [
            'property' => $property,
            'form' => $form,
            'month' => $month,
            'prevMonth' => $month->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthEnd->format('Y-m'),
            'weeks' => $this->buildCalendar($month, $reservations, $unavailabilities),
            'unavailabilities' => $unavailabilities,
        ]);
    }

    #[Route('/{unavailabilityId}/supprimer', name: 'app_host_availability_delete', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function delete(
        Request $request,
        Property $property,
        string $unavailabilityId,
        PropertyUnavailabilityRepository $unavailabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $unavailability = $unavailabilityRepository->find($unavailabilityId);

        if (
            $unavailability !== null
            && $unavailability->getProperty() === $property
            && $this->isCsrfTokenValid('delete_unavailability_' . $unavailabilityId, (string) $request->request->get('_token'))
        ) {
            $entityManager->remove($unavailability);
            $entityManager->flush();
            $this->addFlash('success', 'Période d\'indisponibilité supprimée.');
        }

        return $this->redirectToRoute('app_host_availability_index', ['id' => $property->getId()]);
    }

    private function resolveMonth(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return new \DateTimeImmutable('first day of this month 00:00:00');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01');

        return $parsed instanceof \DateTimeImmutable
            ? $parsed
            : new \DateTimeImmutable('first day of this month 00:00:00');
    }

    /**
     * Construit la grille mensuelle (semaines de 7 jours, lundi en tête).
     *
     * @param list<\App\Entity\Reservation>          $reservations
     * @param list<\App\Entity\PropertyUnavailability> $unavailabilities
     *
     * @return list<list<array{day: int, state: string}|null>>
     */
    private function buildCalendar(\DateTimeImmutable $month, array $reservations, array $unavailabilities): array
    {
        $daysInMonth = (int) $month->format('t');
        $leading = (int) $month->format('N') - 1;

        $cells = array_fill(0, $leading, null);
        for ($day = 1; $day <= $daysInMonth; ++$day) {
            $date = $month->modify(sprintf('+%d days', $day - 1));
            $cells[] = [
                'day' => $day,
                'state' => $this->dayState($date, $reservations, $unavailabilities),
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }

    /**
     * @param list<\App\Entity\Reservation>          $reservations
     * @param list<\App\Entity\PropertyUnavailability> $unavailabilities
     */
    private function dayState(\DateTimeImmutable $date, array $reservations, array $unavailabilities): string
    {
        foreach ($reservations as $reservation) {
            if ($date >= $reservation->getCheckinDate() && $date < $reservation->getCheckoutDate()) {
                return 'reserved';
            }
        }

        foreach ($unavailabilities as $unavailability) {
            if ($date >= $unavailability->getStartDate() && $date < $unavailability->getEndDate()) {
                return 'blocked';
            }
        }

        return 'free';
    }
}
