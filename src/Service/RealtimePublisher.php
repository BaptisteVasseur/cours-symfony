<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RealtimeEvent;
use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class RealtimePublisher
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function publishToUser(User $recipient, string $type, array $payload = []): void
    {
        $userId = $recipient->getId();
        if ($userId === null) {
            return;
        }

        $this->publish($type, $payload, $userId->toRfc4122(), null);
    }

    public function publishToTopic(string $topic, string $type, array $payload = []): void
    {
        $this->publish($type, $payload, null, $topic);
    }

    public function publishReservationChanged(Reservation $reservation, string $type): void
    {
        $payload = $this->reservationPayload($reservation);
        $recipients = [];

        $guest = $reservation->getGuest();
        if ($guest instanceof User && $guest->getId() !== null) {
            $recipients[$guest->getId()->toRfc4122()] = $guest;
        }

        $host = $reservation->getProperty()?->getHost();
        if ($host instanceof User && $host->getId() !== null) {
            $recipients[$host->getId()->toRfc4122()] = $host;
        }

        foreach ($recipients as $recipient) {
            $this->publishToUser($recipient, $type, $payload);
        }
    }

    public function publishAvailabilityChanged(Reservation $reservation): void
    {
        $property = $reservation->getProperty();
        $this->publishPropertyAvailabilityChanged($property, [
            'reservationId' => $reservation->getId()?->toRfc4122(),
            'status' => $reservation->getStatus(),
        ]);
    }

    public function publishPropertyAvailabilityChanged(?Property $property, array $extraPayload = []): void
    {
        $propertyId = $property?->getId();
        if ($propertyId === null) {
            return;
        }

        $payload = ['propertyId' => $propertyId->toRfc4122()] + $extraPayload;

        $this->publishToTopic('property:'.$propertyId->toRfc4122().':availability', 'availability.updated', $payload);
        $this->publishToTopic('search:availability', 'availability.updated', [
            'propertyId' => $propertyId->toRfc4122(),
        ]);
    }

    private function publish(string $type, array $payload, ?string $recipientUserId, ?string $topic): void
    {
        $event = new RealtimeEvent($type, $payload, $recipientUserId, $topic);
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    private function reservationPayload(Reservation $reservation): array
    {
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();

        return [
            'reservationId' => $reservation->getId()?->toRfc4122(),
            'status' => $reservation->getStatus(),
            'propertyId' => $property?->getId()?->toRfc4122(),
            'propertyTitle' => $property?->getTitle(),
            'guestId' => $guest?->getId()?->toRfc4122(),
            'guestName' => $this->displayName($guest),
            'checkinDate' => $reservation->getCheckinDate()?->format('Y-m-d'),
            'checkoutDate' => $reservation->getCheckoutDate()?->format('Y-m-d'),
            'totalPrice' => $reservation->getTotalPrice(),
        ];
    }

    private function displayName(?User $user): ?string
    {
        if (!$user instanceof User) {
            return null;
        }

        $profile = $user->getProfile();
        $name = trim(sprintf('%s %s', $profile?->getFirstName() ?? '', $profile?->getLastName() ?? ''));

        return $name !== '' ? $name : $user->getEmail();
    }
}
