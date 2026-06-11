<?php

declare(strict_types=1);

namespace App\MessageHandler\Schedule;

use App\Message\BookingExpiredMessage;
use App\Message\Schedule\ExpireBookingsMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ExpireBookingsHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(ExpireBookingsMessage $message): void
    {
        $expired = $this->reservationRepository->findPendingExpired();

        foreach ($expired as $reservation) {
            $reservation->setStatus('expired');
            $reservation->setUpdatedAt(new \DateTimeImmutable());

            $guest    = $reservation->getGuest();
            $property = $reservation->getProperty();

            $this->bus->dispatch(new BookingExpiredMessage(
                reservationId:  (string) $reservation->getId(),
                propertyTitle:  $property->getTitle() ?? '',
                guestFirstName: $guest->getProfile()?->getFirstName() ?? $guest->getEmail(),
                guestEmail:     $guest->getEmail(),
                checkinDate:    $reservation->getCheckinDate()?->format('d/m/Y') ?? '',
                checkoutDate:   $reservation->getCheckoutDate()?->format('d/m/Y') ?? '',
            ));
        }

        if (count($expired) > 0) {
            $this->em->flush();
        }
    }
}
