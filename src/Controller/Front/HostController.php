<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\AvailabilityBlockType;
use App\Form\AvailabilityPricingType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use App\Security\Voter\ReservationVoter;
use App\Service\AvailabilityChecker;
use App\Service\ReservationStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote')]
#[IsGranted('ROLE_HOST')]
final class HostController extends AbstractController
{
    /** @var array<string, string> */
    private const REASON_LABELS = [
        'travaux' => 'Travaux',
        'usage_personnel' => 'Usage personnel',
        'autre' => 'Autre',
    ];

    /** @var array<int, string> */
    private const MONTH_LABELS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    #[Route('', name: 'app_host_index', methods: ['GET'])]
    public function index(PropertyRepository $propertyRepository, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/index.html.twig', [
            'properties' => $propertyRepository->findByHost($user),
            'pendingCount' => $reservationRepository->countPendingForHost($user),
        ]);
    }

    #[Route('/reservations', name: 'app_host_reservations', methods: ['GET'])]
    public function reservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/host/reservations.html.twig', [
            'reservations' => $reservationRepository->findByHostForListing($user),
        ]);
    }

    #[Route('/reservation/{id}/confirmer', name: 'app_host_reservation_confirm', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function confirmReservation(
        Request $request,
        Reservation $reservation,
        AvailabilityChecker $availabilityChecker,
        ReservationStatusManager $statusManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('reservation_manage_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Seules les réservations en attente peuvent être confirmées.');

            return $this->redirectToRoute('app_host_reservations');
        }

        // On revérifie la disponibilité : une autre réservation confirmée a pu prendre les dates entre-temps.
        $reasons = $availabilityChecker->getUnavailabilityReasons(
            $reservation->getProperty(),
            $reservation->getCheckinDate(),
            $reservation->getCheckoutDate(),
            (int) $reservation->getGuestsCount(),
        );
        if ($reasons !== []) {
            $this->addFlash('error', 'Impossible de confirmer : ' . implode(' ', $reasons));

            return $this->redirectToRoute('app_host_reservations');
        }

        $statusManager->confirm($reservation, $user);
        $this->addFlash('success', 'Réservation confirmée. Le voyageur a été notifié par email.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/reservation/{id}/annuler', name: 'app_host_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::MANAGE, subject: 'reservation')]
    public function cancelReservation(
        Request $request,
        Reservation $reservation,
        ReservationStatusManager $statusManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('reservation_manage_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_reservations');
        }

        // Un hôte peut refuser une demande (pending) ou annuler une réservation confirmée.
        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut plus être annulée.');

            return $this->redirectToRoute('app_host_reservations');
        }

        // Le motif est obligatoire pour toute annulation.
        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif d\'annulation est obligatoire.');

            return $this->redirectToRoute('app_host_reservations');
        }

        $statusManager->cancel($reservation, $reason, $user);
        $this->addFlash('success', 'Réservation annulée. Les dates sont de nouveau disponibles et le voyageur a été notifié.');

        return $this->redirectToRoute('app_host_reservations');
    }

    #[Route('/logement/{id}/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function calendar(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $today = new \DateTimeImmutable('today');
        $year = (int) $request->query->get('year', (int) $today->format('Y'));
        $month = (int) $request->query->get('month', (int) $today->format('n'));

        // Garde-fous sur les bornes du mois demandé.
        if ($month < 1 || $month > 12) {
            $month = (int) $today->format('n');
            $year = (int) $today->format('Y');
        }

        $monthStart = (new \DateTimeImmutable())->setDate($year, $month, 1)->setTime(0, 0);
        $monthEnd = $monthStart->modify('last day of this month');

        // Données du mois : disponibilités (blocages, tarifs) et réservations confirmées.
        $availabilities = $availabilityRepository->findBetween($property, $monthStart, $monthEnd);
        $reservations = $reservationRepository->findConfirmedBetween($property, $monthStart, $monthEnd);

        $weeks = $this->buildMonthGrid($monthStart, $monthEnd, $today, $availabilities, $reservations);

        $prev = $monthStart->modify('-1 month');
        $next = $monthStart->modify('+1 month');

        // URL d'export iCal (absolue, avec le jeton) si la synchronisation est activée.
        $icalUrl = $property->getIcalToken() !== null
            ? $this->generateUrl(
                'app_property_ical_export',
                ['id' => $property->getId(), 'token' => $property->getIcalToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            : null;

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'monthLabel' => self::MONTH_LABELS[$month] . ' ' . $year,
            'weeks' => $weeks,
            'icalUrl' => $icalUrl,
            'prevYear' => (int) $prev->format('Y'),
            'prevMonth' => (int) $prev->format('n'),
            'nextYear' => (int) $next->format('Y'),
            'nextMonth' => (int) $next->format('n'),
            'blockForm' => $this->createForm(AvailabilityBlockType::class, null, [
                'action' => $this->generateUrl('app_host_block', ['id' => $property->getId()]),
            ])->createView(),
            'pricingForm' => $this->createForm(AvailabilityPricingType::class, null, [
                'action' => $this->generateUrl('app_host_pricing', ['id' => $property->getId()]),
            ])->createView(),
        ]);
    }

    #[Route('/logement/{id}/ical/generer', name: 'app_host_ical_generate', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function generateIcalToken(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('ical_token_' . $property->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        // (Re)génère le jeton : régénérer révoque automatiquement l'ancien lien.
        $property->setIcalToken(bin2hex(random_bytes(32)));
        $entityManager->flush();
        $this->addFlash('success', 'Lien de synchronisation iCal généré.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/logement/{id}/ical/revoquer', name: 'app_host_ical_revoke', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function revokeIcalToken(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('ical_token_' . $property->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $property->setIcalToken(null);
        $entityManager->flush();
        $this->addFlash('success', 'Lien de synchronisation iCal révoqué.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/logement/{id}/indisponibilite', name: 'app_host_block', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function block(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(AvailabilityBlockType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var \DateTimeImmutable $start */
            $start = $data['startDate']->setTime(0, 0);
            /** @var \DateTimeImmutable $end */
            $end = $data['endDate']->setTime(0, 0);
            $reason = $data['reason'];

            if ($start > $end) {
                $this->addFlash('error', 'La date de fin doit être postérieure ou égale à la date de début.');

                return $this->redirectToCalendar($property, $start);
            }

            $existing = $this->indexByDate($availabilityRepository->findBetween($property, $start, $end));

            foreach ($this->datesBetween($start, $end) as $day) {
                $availability = $existing[$day->format('Y-m-d')] ?? null;
                if ($availability === null) {
                    $availability = (new PropertyAvailability())
                        ->setProperty($property)
                        ->setAvailableDate($day);
                    $entityManager->persist($availability);
                }
                $availability->setIsAvailable(false);
                $availability->setBlockReason($reason);
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf(
                'Période bloquée du %s au %s (%s).',
                $start->format('d/m/Y'),
                $end->format('d/m/Y'),
                self::REASON_LABELS[$reason] ?? $reason,
            ));

            return $this->redirectToCalendar($property, $start);
        }

        $this->addFlash('error', 'Le formulaire d\'indisponibilité est invalide.');

        return $this->redirectToCalendar($property, new \DateTimeImmutable('today'));
    }

    #[Route('/logement/{id}/disponibilite', name: 'app_host_unblock', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function unblock(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $dateString = (string) $request->request->get('date');
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('availability_unblock', $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToCalendar($property, new \DateTimeImmutable('today'));
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateString) ?: new \DateTimeImmutable('today');
        $availabilities = $availabilityRepository->findBetween($property, $date, $date);

        foreach ($availabilities as $availability) {
            // On lève le blocage en conservant un éventuel tarif / séjour minimum.
            if ($availability->getPriceOverride() === null && $availability->getMinimumStay() === null) {
                $entityManager->remove($availability);
            } else {
                $availability->setIsAvailable(true);
                $availability->setBlockReason(null);
            }
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('Le %s est de nouveau disponible.', $date->format('d/m/Y')));

        return $this->redirectToCalendar($property, $date);
    }

    #[Route('/logement/{id}/tarification', name: 'app_host_pricing', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function pricing(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(AvailabilityPricingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var \DateTimeImmutable $start */
            $start = $data['startDate']->setTime(0, 0);
            /** @var \DateTimeImmutable $end */
            $end = $data['endDate']->setTime(0, 0);
            $price = $data['priceOverride'];
            $minStay = $data['minimumStay'];

            if ($start > $end) {
                $this->addFlash('error', 'La date de fin doit être postérieure ou égale à la date de début.');

                return $this->redirectToCalendar($property, $start);
            }

            if ($price === null && $minStay === null) {
                $this->addFlash('error', 'Renseignez au moins un tarif ou un séjour minimum.');

                return $this->redirectToCalendar($property, $start);
            }

            $existing = $this->indexByDate($availabilityRepository->findBetween($property, $start, $end));

            foreach ($this->datesBetween($start, $end) as $day) {
                $availability = $existing[$day->format('Y-m-d')] ?? null;
                if ($availability === null) {
                    $availability = (new PropertyAvailability())
                        ->setProperty($property)
                        ->setAvailableDate($day);
                    $entityManager->persist($availability);
                }
                if ($price !== null) {
                    $availability->setPriceOverride(number_format((float) $price, 2, '.', ''));
                }
                if ($minStay !== null) {
                    $availability->setMinimumStay((int) $minStay);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf(
                'Tarification mise à jour du %s au %s.',
                $start->format('d/m/Y'),
                $end->format('d/m/Y'),
            ));

            return $this->redirectToCalendar($property, $start);
        }

        $this->addFlash('error', 'Le formulaire de tarification est invalide.');

        return $this->redirectToCalendar($property, new \DateTimeImmutable('today'));
    }

    private function redirectToCalendar(Property $property, \DateTimeImmutable $date): Response
    {
        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
        ]);
    }

    /**
     * @return \DateTimeImmutable[] Liste des jours de [start, end] (bornes incluses).
     */
    private function datesBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));

        return iterator_to_array($period);
    }

    /**
     * @param list<PropertyAvailability> $availabilities
     *
     * @return array<string, PropertyAvailability>
     */
    private function indexByDate(array $availabilities): array
    {
        $map = [];
        foreach ($availabilities as $availability) {
            $map[$availability->getAvailableDate()->format('Y-m-d')] = $availability;
        }

        return $map;
    }

    /**
     * Construit la grille mensuelle (semaines commençant le lundi) pour le template.
     *
     * @param list<PropertyAvailability> $availabilities
     * @param list<\App\Entity\Reservation> $reservations
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildMonthGrid(
        \DateTimeImmutable $monthStart,
        \DateTimeImmutable $monthEnd,
        \DateTimeImmutable $today,
        array $availabilities,
        array $reservations,
    ): array {
        $availByDate = $this->indexByDate($availabilities);

        // Indexe les jours réservés (de l'arrivée à la veille du départ) avec le nom du voyageur.
        $reservedByDate = [];
        foreach ($reservations as $reservation) {
            $checkin = $reservation->getCheckinDate();
            $checkout = $reservation->getCheckoutDate();
            if ($checkin === null || $checkout === null) {
                continue;
            }
            $guestProfile = $reservation->getGuest()?->getProfile();
            $guestName = $guestProfile && ($guestProfile->getFirstName() || $guestProfile->getLastName())
                ? trim(($guestProfile->getFirstName() ?? '') . ' ' . ($guestProfile->getLastName() ?? ''))
                : ($reservation->getGuest()?->getEmail() ?? 'Voyageur');

            foreach ($this->datesBetween($checkin->setTime(0, 0), $checkout->modify('-1 day')->setTime(0, 0)) as $day) {
                $reservedByDate[$day->format('Y-m-d')] = $guestName;
            }
        }

        // La grille démarre le lundi de la semaine du 1er du mois (ISO : lundi = 1).
        $gridStart = $monthStart->modify('-' . (((int) $monthStart->format('N')) - 1) . ' days');
        $gridEnd = $monthEnd->modify('+' . (7 - (int) $monthEnd->format('N')) . ' days');

        $weeks = [];
        $week = [];
        foreach ($this->datesBetween($gridStart, $gridEnd) as $index => $day) {
            $key = $day->format('Y-m-d');
            $availability = $availByDate[$key] ?? null;
            $blocked = $availability !== null && !$availability->isAvailable();

            $week[] = [
                'day' => (int) $day->format('j'),
                'date' => $key,
                'inMonth' => $day >= $monthStart && $day <= $monthEnd,
                'past' => $day < $today,
                'blocked' => $blocked,
                'reason' => $blocked ? (self::REASON_LABELS[$availability->getBlockReason()] ?? $availability->getBlockReason()) : null,
                'reserved' => isset($reservedByDate[$key]),
                'guest' => $reservedByDate[$key] ?? null,
                'price' => $availability?->getPriceOverride(),
                'minStay' => $availability?->getMinimumStay(),
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }
}
