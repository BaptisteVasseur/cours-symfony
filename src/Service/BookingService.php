<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Message\BookingCancelledMessage;
use App\Message\BookingConfirmedMessage;
use App\Message\BookingRequestedMessage;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BookingService
{
    private const SERVICE_FEE_RATE   = 0.05;
    private const PENDING_TTL_HOURS  = 48;

    public function __construct(
        private readonly EntityManagerInterface          $em,
        private readonly MessageBusInterface             $bus,
        private readonly PropertyAvailabilityRepository  $availabilityRepository,
        private readonly ReservationRepository           $reservationRepository,
        private readonly UrlGeneratorInterface           $urlGenerator,
    ) {}

    /**
     * @throws \RuntimeException if business rules are violated
     */
    public function createBooking(Property $property, User $guest, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, int $guestsCount): Reservation
    {
        // R1 — logement publié
        if ($property->getStatus() !== 'published') {
            throw new \RuntimeException('Ce logement n\'est pas disponible à la réservation.');
        }

        // R2 — capacité
        if ($property->getMaxGuests() !== null && $guestsCount > $property->getMaxGuests()) {
            throw new \RuntimeException(sprintf(
                'Ce logement accepte %d voyageur(s) maximum.',
                $property->getMaxGuests(),
            ));
        }

        // R3 — dates cohérentes
        if ($checkOut <= $checkIn) {
            throw new \RuntimeException('La date de départ doit être postérieure à la date d\'arrivée.');
        }
        if ($checkIn < new \DateTimeImmutable('today')) {
            throw new \RuntimeException('La date d\'arrivée ne peut pas être dans le passé.');
        }

        // R4 — pas de date bloquée dans la plage
        $blocked = $this->availabilityRepository->findBlockedInPeriod($property, $checkIn, $checkOut);
        if (count($blocked) > 0) {
            throw new \RuntimeException('Certaines dates sélectionnées ne sont pas disponibles.');
        }

        // R5a — durée de séjour minimum
        $minStay = $this->availabilityRepository->getMaxMinimumStayInPeriod($property, $checkIn, $checkOut);
        $nights  = (int) $checkIn->diff($checkOut)->days;
        if ($minStay !== null && $nights < $minStay) {
            throw new \RuntimeException(sprintf(
                'La durée minimale de séjour sur cette période est de %d nuit(s).',
                $minStay,
            ));
        }

        // R5b — pas de chevauchement avec une réservation CONFIRMED
        $overlapping = $this->reservationRepository->findByPropertyAndPeriod($property, $checkIn, $checkOut);
        foreach ($overlapping as $existing) {
            if ($existing->getStatus() === 'confirmed') {
                throw new \RuntimeException('Ces dates sont déjà réservées.');
            }
        }

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkIn);
        $reservation->setCheckoutDate($checkOut);
        $reservation->setGuestsCount($guestsCount);
        $reservation->setCreatedAt(new \DateTimeImmutable());
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $reservation->setCurrency('EUR');

        [$total, $cleaning, $serviceFee] = $this->computePrices($property, $checkIn, $checkOut);
        $reservation->setTotalPrice((string) $total);
        $reservation->setCleaningFee((string) $cleaning);
        $reservation->setServiceFee((string) $serviceFee);

        if ($property->getSecurityDeposit() !== null) {
            $reservation->setSecurityDeposit($property->getSecurityDeposit());
        }

        if ($property->isInstantBooking()) {
            $reservation->setStatus('confirmed');
            $reservation->setExpiresAt(null);
        } else {
            $reservation->setStatus('pending');
            $reservation->setExpiresAt(new \DateTimeImmutable('+' . self::PENDING_TTL_HOURS . ' hours'));
        }

        $this->em->persist($reservation);
        $this->em->flush();

        $host = $property->getHost();
        $this->bus->dispatch(new BookingRequestedMessage(
            reservationId:  (string) $reservation->getId(),
            propertyTitle:  $property->getTitle() ?? '',
            guestFirstName: $guest->getProfile()?->getFirstName() ?? $guest->getEmail(),
            guestEmail:     $guest->getEmail(),
            hostEmail:      $host?->getEmail() ?? '',
            checkinDate:    $checkIn->format('d/m/Y'),
            checkoutDate:   $checkOut->format('d/m/Y'),
            guestsCount:    $guestsCount,
            totalPrice:     $total,
            currency:       'EUR',
            hostDashboardUrl: $this->urlGenerator->generate(
                'host_dashboard',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ));

        return $reservation;
    }

    public function confirm(Reservation $reservation): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \RuntimeException('Seule une réservation en attente peut être confirmée.');
        }

        $reservation->setStatus('confirmed');
        $reservation->setExpiresAt(null);
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $guest    = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host     = $property?->getHost();

        $this->bus->dispatch(new BookingConfirmedMessage(
            reservationId:  (string) $reservation->getId(),
            propertyTitle:  $property?->getTitle() ?? '',
            guestFirstName: $guest->getProfile()?->getFirstName() ?? $guest->getEmail(),
            guestEmail:     $guest->getEmail(),
            hostFirstName:  $host?->getProfile()?->getFirstName() ?? ($host?->getEmail() ?? ''),
            hostEmail:      $host?->getEmail() ?? '',
            checkinDate:    $reservation->getCheckinDate()?->format('d/m/Y') ?? '',
            checkoutDate:   $reservation->getCheckoutDate()?->format('d/m/Y') ?? '',
            totalPrice:     (float) $reservation->getTotalPrice(),
            currency:       $reservation->getCurrency() ?? 'EUR',
            bookingUrl:     $this->urlGenerator->generate(
                'booking_my_bookings',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ));
    }

    public function reject(Reservation $reservation, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \RuntimeException('Seule une réservation en attente peut être refusée.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Un motif de refus est obligatoire.');
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $reservation->setCancelledBy('host');
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->dispatchCancelled($reservation);
    }

    public function rejectByAdmin(Reservation $reservation, string $reason): void
    {
        if ($reservation->getStatus() !== 'pending') {
            throw new \RuntimeException('Seule une réservation en attente peut être refusée.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Un motif de refus est obligatoire.');
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $reservation->setCancelledBy('admin');
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->dispatchCancelled($reservation);
    }

    public function cancel(Reservation $reservation, string $reason, string $cancelledBy): void
    {
        if (in_array($reservation->getStatus(), ['completed', 'cancelled', 'expired'], true)) {
            throw new \RuntimeException('Cette réservation ne peut plus être annulée.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Un motif d\'annulation est obligatoire.');
        }

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $reservation->setCancelledBy($cancelledBy);
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->dispatchCancelled($reservation);
    }

    public function cancelBySystem(Reservation $reservation, string $reason): void
    {
        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $reservation->setCancelledBy('system');
        $reservation->setUpdatedAt(new \DateTimeImmutable());
    }

    private function dispatchCancelled(Reservation $reservation): void
    {
        $guest    = $reservation->getGuest();
        $property = $reservation->getProperty();
        $host     = $property?->getHost();

        $this->bus->dispatch(new BookingCancelledMessage(
            reservationId:       (string) $reservation->getId(),
            propertyTitle:       $property?->getTitle() ?? '',
            guestFirstName:      $guest->getProfile()?->getFirstName() ?? $guest->getEmail(),
            guestEmail:          $guest->getEmail(),
            hostEmail:           $host?->getEmail() ?? '',
            checkinDate:         $reservation->getCheckinDate()?->format('d/m/Y') ?? '',
            checkoutDate:        $reservation->getCheckoutDate()?->format('d/m/Y') ?? '',
            cancellationReason:  $reservation->getCancellationReason(),
            cancelledBy:         $reservation->getCancelledBy() ?? 'system',
        ));
    }

    /** @return array{float, float, float} [total, cleaningFee, serviceFee] */
    private function computePrices(Property $property, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): array
    {
        $nights       = (int) $checkIn->diff($checkOut)->days;
        $defaultPrice = (float) ($property->getPricePerNight() ?? 0);
        $cleaning     = (float) ($property->getCleaningFee() ?? 0);

        // Use per-night overrides when available (same logic as BookingApiController)
        $overrides = $this->availabilityRepository->getPriceOverridesInPeriod($property, $checkIn, $checkOut);
        $base = 0.0;
        for ($i = 0; $i < $nights; $i++) {
            $night = $checkIn->modify("+{$i} days");
            $base += $overrides[$night->format('Y-m-d')] ?? $defaultPrice;
        }

        $serviceFee = round($base * self::SERVICE_FEE_RATE, 2);
        $total      = round($base + $cleaning + $serviceFee, 2);

        return [$total, $cleaning, $serviceFee];
    }
}
