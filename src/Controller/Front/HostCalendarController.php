<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Form\AvailabilityBlockType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes/{id}/calendrier')]
#[IsGranted('ROLE_HOST')]
#[IsGranted(PropertyVoter::EDIT, subject: 'property')]
final class HostCalendarController extends AbstractController
{
    #[Route('', name: 'app_host_property_calendar', methods: ['GET', 'POST'])]
    public function calendar(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
        AvailabilityService $availabilityService,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($property->getICalExportToken() === null) {
            $property->regenerateICalExportToken();
            $entityManager->flush();
        }

        $form = $this->createForm(AvailabilityBlockType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $availabilityService->blockPeriod($property, $data['dateStart'], $data['dateEnd'], $data['reason'] ?? null);
                $this->addFlash('success', 'Periode bloquee.');

                return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()]);
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        $monthStart = $this->resolveMonthStart($request);
        $monthEnd = $monthStart->modify('first day of next month');
        $gridStart = $monthStart->modify('monday this week');
        $gridEnd = $monthEnd->modify('sunday this week')->modify('+1 day');
        $blocks = $availabilityRepository->findBlockedForPeriod($property, $gridStart, $gridEnd);
        $reservations = $reservationRepository->findConfirmedForPeriod($property, $gridStart, $gridEnd);

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'form' => $form,
            'monthStart' => $monthStart,
            'previousMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'days' => $this->buildCalendarDays($gridStart, $gridEnd, $monthStart, $blocks, $reservations),
            'blocks' => $blocks,
            'reservations' => $reservations,
            'iCalUrl' => $this->generateUrl('app_property_calendar_ics', [
                'id' => $property->getId(),
                'token' => $property->getICalExportToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/ical/regenerer', name: 'app_host_property_ical_regenerate', methods: ['POST'])]
    public function regenerateToken(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('regenerate_ical_' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $property->regenerateICalExportToken();
        $entityManager->flush();
        $this->addFlash('success', 'Lien iCal regenere.');

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()]);
    }

    #[Route('/blocs/{availability}/supprimer', name: 'app_host_property_availability_delete', methods: ['POST'])]
    public function deleteBlock(
        Request $request,
        Property $property,
        PropertyAvailability $availability,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($availability->getProperty()?->getId() !== $property->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_availability_' . $availability->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($availability);
        $entityManager->flush();
        $this->addFlash('success', 'Periode debloquee.');

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()]);
    }

    private function resolveMonthStart(Request $request): \DateTimeImmutable
    {
        $month = (string) $request->query->get('month', '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $month);

        if ($date === false) {
            return (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
        }

        return $date->setTime(0, 0);
    }

    /**
     * @param list<PropertyAvailability> $blocks
     * @param list<\App\Entity\Reservation> $reservations
     * @return list<array{date: \DateTimeImmutable, currentMonth: bool, blocks: list<PropertyAvailability>, reservations: list<\App\Entity\Reservation>}>
     */
    private function buildCalendarDays(
        \DateTimeImmutable $gridStart,
        \DateTimeImmutable $gridEnd,
        \DateTimeImmutable $monthStart,
        array $blocks,
        array $reservations,
    ): array {
        $days = [];

        foreach (new \DatePeriod($gridStart, new \DateInterval('P1D'), $gridEnd) as $date) {
            $dayStart = \DateTimeImmutable::createFromInterface($date);
            $dayEnd = $dayStart->modify('+1 day');

            $days[] = [
                'date' => $dayStart,
                'currentMonth' => $dayStart->format('Y-m') === $monthStart->format('Y-m'),
                'blocks' => array_values(array_filter($blocks, static fn (PropertyAvailability $block): bool => $block->getDateStart() < $dayEnd && $block->getDateEnd() > $dayStart)),
                'reservations' => array_values(array_filter($reservations, static fn (\App\Entity\Reservation $reservation): bool => $reservation->getCheckinDate() < $dayEnd && $reservation->getCheckoutDate() > $dayStart)),
            ];
        }

        return $days;
    }
}
