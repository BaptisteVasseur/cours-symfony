<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Form\BlockDatesType;
use App\Form\SpecialPriceType;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/logements/{id}/calendrier', name: 'host_calendar_')]
#[IsGranted('ROLE_HOST')]
final class HostCalendarController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(
        Property $property,
        AvailabilityService $availabilityService,
        ReservationRepository $reservationRepository,
    ): Response {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $from = new \DateTimeImmutable('first day of this month');
        $to   = $from->modify('+12 months -1 day');

        $blockedMap   = $availabilityService->getBlockedDateMap($property, $from, $to);
        $reservations = $reservationRepository->findByPropertyAndPeriod($property, $from, $to);

        // Build reservation date map: Y-m-d → status
        $reservationMap = [];
        foreach ($reservations as $reservation) {
            $ci     = $reservation->getCheckinDate();
            $co     = $reservation->getCheckoutDate();
            $status = $reservation->getStatus();
            if (!$ci || !$co) {
                continue;
            }
            $current = $ci;
            while ($current < $co) {
                $key = $current->format('Y-m-d');
                // confirmed takes priority over pending
                if (!isset($reservationMap[$key]) || $status === 'confirmed') {
                    $reservationMap[$key] = $status;
                }
                $current = $current->modify('+1 day');
            }
        }

        // Pre-compute calendar months (12 months from today)
        $frMonths = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $calendarMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $first = $from->modify("+{$i} months");
            $calendarMonths[] = [
                'year'         => (int) $first->format('Y'),
                'month'        => (int) $first->format('n'),
                'daysInMonth'  => (int) $first->format('t'),
                'firstWeekday' => (int) $first->format('N'), // ISO: 1=Mon … 7=Sun
                'label'        => $frMonths[(int) $first->format('n') - 1] . ' ' . $first->format('Y'),
            ];
        }

        $blockForm = $this->createForm(BlockDatesType::class, null, [
            'action' => $this->generateUrl('host_calendar_block', ['id' => $property->getId()]),
        ]);

        $specialPriceForm = $this->createForm(SpecialPriceType::class, null, [
            'action' => $this->generateUrl('host_calendar_special_price', ['id' => $property->getId()]),
        ]);

        // Build special-price date map: Y-m-d → priceOverride (available days with overridden price)
        $specialPriceMap = [];
        foreach ($property->getAvailabilities() as $av) {
            if ($av->isAvailable() && $av->getPriceOverride() !== null) {
                $key = $av->getAvailableDate()?->format('Y-m-d');
                if ($key !== null) {
                    $specialPriceMap[$key] = (float) $av->getPriceOverride();
                }
            }
        }

        return $this->render('host/calendar.html.twig', [
            'property'         => $property,
            'blockedMap'       => $blockedMap,
            'reservationMap'   => $reservationMap,
            'calendarMonths'   => $calendarMonths,
            'blockForm'        => $blockForm,
            'specialPriceForm' => $specialPriceForm,
            'specialPriceMap'  => $specialPriceMap,
        ]);
    }

    #[Route('/bloquer', name: 'block', methods: ['POST'])]
    public function block(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(BlockDatesType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \DateTime $start */
            $start = $form->get('startDate')->getData();
            /** @var \DateTime $end */
            $end = $form->get('endDate')->getData();

            $startImmutable = \DateTimeImmutable::createFromInterface($start);
            $endImmutable   = \DateTimeImmutable::createFromInterface($end);

            if ($endImmutable < $startImmutable) {
                $this->addFlash('error', 'La date de fin doit être après la date de début.');
                return $this->redirectToRoute('host_calendar_show', ['id' => $property->getId()]);
            }

            $blockReason = $form->get('blockReason')->getData() ?: null;

            try {
                $availabilityService->blockPeriod(
                    $property,
                    $startImmutable,
                    $endImmutable,
                    $blockReason,
                );
                $this->addFlash('success', 'Dates bloquées avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Formulaire invalide. Vérifiez les dates.');
        }

        return $this->redirectToRoute('host_calendar_show', ['id' => $property->getId()]);
    }

    #[Route('/tarif-special', name: 'special_price', methods: ['POST'])]
    public function specialPrice(
        Property $property,
        Request $request,
        AvailabilityService $availabilityService,
    ): Response {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(SpecialPriceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \DateTime $start */
            $start = $form->get('startDate')->getData();
            /** @var \DateTime $end */
            $end = $form->get('endDate')->getData();

            $startImmutable = \DateTimeImmutable::createFromInterface($start);
            $endImmutable   = \DateTimeImmutable::createFromInterface($end);

            if ($endImmutable < $startImmutable) {
                $this->addFlash('error', 'La date de fin doit être après la date de début.');
                return $this->redirectToRoute('host_calendar_show', ['id' => $property->getId()]);
            }

            $price = (float) $form->get('priceOverride')->getData();

            try {
                $availabilityService->setPricePeriod($property, $startImmutable, $endImmutable, $price);
                $this->addFlash('success', 'Tarif spécial appliqué avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Formulaire invalide. Vérifiez les champs.');
        }

        return $this->redirectToRoute('host_calendar_show', ['id' => $property->getId()]);
    }

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function settings(
        Property $property,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'regen_token') {
            if (!$this->isCsrfTokenValid('regen_token_' . $property->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('host_calendar_settings', ['id' => $property->getId()]);
            }
            $property->regenerateCalendarToken();
            $em->flush();
            $this->addFlash('success', 'Lien iCal régénéré.');
        }

        return $this->render('host/settings.html.twig', [
            'property' => $property,
        ]);
    }
}
