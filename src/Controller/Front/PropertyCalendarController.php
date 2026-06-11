<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyBlockedPeriod;
use App\Form\UnavailabilityType;
use App\Security\Voter\PropertyVoter;
use App\Service\HostCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes/{id}/calendrier')]
#[IsGranted('ROLE_USER')]
final class PropertyCalendarController extends AbstractController
{
    public function __construct(
        private readonly HostCalendar $hostCalendar,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_account_property_calendar', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function show(Request $request, Property $property): Response
    {
        $view = $request->query->get('view', 'month') === 'week' ? 'week' : 'month';
        try {
            $anchor = new \DateTimeImmutable($request->query->get('date', 'today'));
        } catch (\Exception) {
            $anchor = new \DateTimeImmutable('today');
        }

        $period = new PropertyBlockedPeriod();
        $period->setProperty($property);
        $form = $this->createForm(UnavailabilityType::class, $period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $redirect = $this->blockPeriod($property, $period);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $calendar = $view === 'week'
            ? $this->hostCalendar->buildWeek($property, $anchor)
            : $this->hostCalendar->buildMonth($property, $anchor);

        return $this->render('front/account/calendar.html.twig', [
            'property' => $property,
            'view' => $view,
            'anchor' => $anchor,
            'calendar' => $calendar,
            'form' => $form,
            'blockedPeriods' => $this->hostCalendar->upcomingBlockedPeriods($property),
        ]);
    }

    #[Route('/indisponibilite/{periodId}/supprimer', name: 'app_account_property_calendar_unblock', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function unblock(Request $request, Property $property, string $periodId): Response
    {
        if (!$this->isCsrfTokenValid('unblock-' . $periodId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $period = $this->entityManager->getRepository(PropertyBlockedPeriod::class)->find($periodId);
        if ($period === null || $period->getProperty()?->getId()?->equals($property->getId()) !== true) {
            throw $this->createNotFoundException('Période introuvable.');
        }

        $this->entityManager->remove($period);
        $this->entityManager->flush();

        $this->addFlash('success', 'La période a été libérée : les dates sont de nouveau réservables.');

        return $this->redirectToCalendar($property, $request);
    }

    private function blockPeriod(Property $property, PropertyBlockedPeriod $period): ?Response
    {
        $start = $period->getStartAt();
        $end = $period->getEndAt();

        if ($start === null || $end === null || $end <= $start) {
            $this->addFlash('error', 'La fin de la période doit être postérieure à son début.');

            return null;
        }

        $conflicts = $this->hostCalendar->findBlockingReservations($property, $start, $end);
        if ($conflicts !== []) {
            $first = $conflicts[0];
            $this->addFlash('error', sprintf(
                'Impossible de bloquer cette période : une réservation occupe déjà le créneau du %s au %s.',
                $first->getCheckinDate()->format('d/m/Y'),
                $first->getCheckoutDate()->format('d/m/Y'),
            ));

            return null;
        }

        $this->entityManager->persist($period);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf(
            'Période bloquée du %s au %s (%s).',
            $start->format('d/m/Y H:i'),
            $end->format('d/m/Y H:i'),
            $period->getReason() ?? 'sans motif',
        ));

        return $this->redirectToRoute('app_account_property_calendar', [
            'id' => $property->getId(),
            'view' => 'month',
            'date' => $start->format('Y-m-d'),
        ]);
    }

    private function redirectToCalendar(Property $property, Request $request): Response
    {
        return $this->redirectToRoute('app_account_property_calendar', [
            'id' => $property->getId(),
            'view' => $request->request->get('view', 'month'),
            'date' => $request->request->get('date'),
        ]);
    }
}
