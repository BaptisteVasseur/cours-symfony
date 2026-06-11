<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyICalSync;
use App\Entity\User;
use App\Form\PropertyAvailabilityBlockType;
use App\Form\PropertyAvailabilityConfigureType;
use App\Form\PropertyICalSyncType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\AvailabilityService;
use App\Service\IcalImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes')]
#[IsGranted('ROLE_USER')]
final class PropertyCalendarController extends AbstractController
{
    #[Route('/{id}/calendrier', name: 'app_property_calendar', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function calendar(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        PropertyAvailabilityRepository $availabilityRepository,
        PropertyICalSyncRepository $iCalSyncRepository,
        ReservationRepository $reservationRepository,
        AvailabilityService $availabilityService,
        IcalImportService $icalImportService,
        EntityManagerInterface $entityManager,
    ): Response {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('m'));

        if ($month < 1) {
            $month = 12;
            --$year;
        } elseif ($month > 12) {
            $month = 1;
            ++$year;
        }

        if (
            $request->isMethod('POST')
            && $this->isCsrfTokenValid('property_calendar_revoke_token', (string) $request->request->get('_token'))
            && $request->request->get('_action') === 'revoke_token'
        ) {
            $property->regenerateCalendarToken();
            $entityManager->flush();
            $this->addFlash('success', 'Le lien iCal a été régénéré. Les anciennes URL ne sont plus valides.');

            return $this->redirectToRoute('app_property_calendar', [
                'id' => $property->getId(),
                'year' => $year,
                'month' => $month,
            ]);
        }

        $iCalSync = $iCalSyncRepository->findOneByProperty($property) ?? new PropertyICalSync();
        if ($iCalSync->getProperty() === null) {
            $iCalSync->setProperty($property);
            $iCalSync->setProviderName('Calendrier externe');
        }

        $blockForm = $this->createForm(PropertyAvailabilityBlockType::class);
        $blockForm->handleRequest($request);

        if ($blockForm->isSubmitted() && $blockForm->isValid()) {
            $data = $blockForm->getData();
            $startDate = $data['startDate'];
            $endDate = $data['endDate'];

            if ($startDate >= $endDate) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
            } elseif ($availabilityService->existsConfirmedOverlap($property, $startDate, $endDate)) {
                $this->addFlash('error', 'Impossible de bloquer ces dates : une réservation confirmée chevauche cette période.');
            } else {
                $blocked = $availabilityService->blockDates($property, $startDate, $endDate, $data['reason'] ?? null);
                $this->addFlash('success', sprintf('%d jour(s) bloqué(s) avec succès.', $blocked));
            }

            return $this->redirectToRoute('app_property_calendar', [
                'id' => $property->getId(),
                'year' => $year,
                'month' => $month,
            ]);
        }

        $configureForm = $this->createForm(PropertyAvailabilityConfigureType::class);
        $configureForm->handleRequest($request);

        if ($configureForm->isSubmitted() && $configureForm->isValid()) {
            $data = $configureForm->getData();
            $startDate = $data['startDate'];
            $endDate = $data['endDate'];
            $priceOverride = $data['priceOverride'] !== null && $data['priceOverride'] !== ''
                ? (string) $data['priceOverride']
                : null;
            $minimumStay = $data['minimumStay'] !== null && $data['minimumStay'] !== ''
                ? (int) $data['minimumStay']
                : null;

            try {
                $configured = $availabilityService->configureDates(
                    $property,
                    $startDate,
                    $endDate,
                    $priceOverride,
                    $minimumStay,
                );
                $this->addFlash('success', sprintf('%d jour(s) configuré(s).', $configured));
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_property_calendar', [
                'id' => $property->getId(),
                'year' => $year,
                'month' => $month,
            ]);
        }

        $iCalForm = $this->createForm(PropertyICalSyncType::class, $iCalSync);
        $iCalForm->handleRequest($request);

        if ($iCalForm->isSubmitted() && $iCalForm->isValid()) {
            if ($iCalSync->getId() === null) {
                $entityManager->persist($iCalSync);
            }
            $entityManager->flush();

            if ($request->request->get('_action') === 'ical_sync_now') {
                try {
                    $result = $icalImportService->sync($iCalSync);
                    $this->addFlash('success', sprintf(
                        'Synchronisation terminée : %d importé(s), %d ignoré(s), %d supprimé(s).',
                        $result['imported'],
                        $result['skipped'],
                        $result['removed'],
                    ));
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Échec de la synchronisation : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('success', 'URL iCal enregistrée.');
            }

            return $this->redirectToRoute('app_property_calendar', [
                'id' => $property->getId(),
                'year' => $year,
                'month' => $month,
            ]);
        }

        $unblockStart = $request->request->get('unblock_start');
        $unblockEnd = $request->request->get('unblock_end');

        if (
            $request->isMethod('POST')
            && !$blockForm->isSubmitted()
            && !$configureForm->isSubmitted()
            && !$iCalForm->isSubmitted()
            && $this->isCsrfTokenValid('property_availability_unblock', (string) $request->request->get('_token'))
            && $unblockStart
            && $unblockEnd
        ) {
            $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $unblockStart);
            $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $unblockEnd);

            if ($startDate === false || $endDate === false || $startDate >= $endDate) {
                $this->addFlash('error', 'Période de déblocage invalide.');
            } else {
                $unblocked = $availabilityService->unblockDates($property, $startDate, $endDate);
                $this->addFlash('success', sprintf('%d jour(s) débloqué(s).', $unblocked));
            }

            return $this->redirectToRoute('app_property_calendar', [
                'id' => $property->getId(),
                'year' => $year,
                'month' => $month,
            ]);
        }

        $availabilities = $availabilityRepository->findForMonth($property, $year, $month);
        $reservations = $reservationRepository->findConfirmedOverlappingMonth($property, $year, $month);

        $blockedDays = [];
        foreach ($availabilities as $availability) {
            if (!$availability->isAvailable()) {
                $blockedDays[$availability->getAvailableDate()->format('Y-m-d')] = $availability;
            }
        }

        $calendarWeeks = $this->buildCalendarWeeks($year, $month, $blockedDays, $reservations, $availabilities);

        $currentMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $previousMonth = $currentMonth->modify('-1 month');
        $nextMonth = $currentMonth->modify('+1 month');
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM yyyy');

        return $this->render('front/account/calendar.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'monthLabel' => $formatter->format($currentMonth),
            'calendarWeeks' => $calendarWeeks,
            'blockedDays' => $blockedDays,
            'reservations' => $reservations,
            'blockForm' => $blockForm,
            'configureForm' => $configureForm,
            'iCalForm' => $iCalForm,
            'iCalSync' => $iCalSync,
            'previousMonth' => $previousMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

    /**
     * @param array<string, \App\Entity\PropertyAvailability> $blockedDays
     * @param list<\App\Entity\Reservation>                   $reservations
     * @param list<\App\Entity\PropertyAvailability>           $availabilities
     *
     * @return list<list<array{date: ?\DateTimeImmutable, status: string, label: ?string}>>
     */
    private function buildCalendarWeeks(int $year, int $month, array $blockedDays, array $reservations, array $availabilities): array
    {
        $specialDays = [];
        foreach ($availabilities as $availability) {
            if ($availability->isAvailable() && ($availability->getPriceOverride() !== null || $availability->getMinimumStay() !== null)) {
                $specialDays[$availability->getAvailableDate()->format('Y-m-d')] = $availability;
            }
        }

        $firstDay = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstDay->format('t');
        $startWeekday = (int) $firstDay->format('N');

        $weeks = [];
        $week = [];

        for ($i = 1; $i < $startWeekday; ++$i) {
            $week[] = ['date' => null, 'status' => 'empty', 'label' => null];
        }

        for ($day = 1; $day <= $daysInMonth; ++$day) {
            $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
            $key = $date->format('Y-m-d');
            $status = 'available';
            $label = null;

            if (isset($blockedDays[$key])) {
                $status = 'blocked';
                $label = $blockedDays[$key]->getReason();
            } elseif (isset($specialDays[$key])) {
                $status = 'special';
                $parts = [];
                if ($specialDays[$key]->getPriceOverride() !== null) {
                    $parts[] = $specialDays[$key]->getPriceOverride() . ' €';
                }
                if ($specialDays[$key]->getMinimumStay() !== null) {
                    $parts[] = 'min ' . $specialDays[$key]->getMinimumStay() . 'n';
                }
                $label = implode(' · ', $parts);
            } else {
                foreach ($reservations as $reservation) {
                    if ($date >= $reservation->getCheckinDate() && $date < $reservation->getCheckoutDate()) {
                        $status = 'reserved';
                        $guest = $reservation->getGuest();
                        $label = $guest instanceof User
                            ? trim(($guest->getProfile()?->getFirstName() ?? '') . ' ' . ($guest->getProfile()?->getLastName() ?? ''))
                            : null;
                        break;
                    }
                }
            }

            $week[] = ['date' => $date, 'status' => $status, 'label' => $label];

            if (\count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if ($week !== []) {
            while (\count($week) < 7) {
                $week[] = ['date' => null, 'status' => 'empty', 'label' => null];
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}
