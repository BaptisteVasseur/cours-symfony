<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fabriques minimales d'entités pour les tests du parcours de réservation.
 * Suppose une propriété $this->em (EntityManagerInterface) définie dans le setUp.
 */
trait ReservationFactoryTrait
{
    private function makeUser(EntityManagerInterface $em, string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPasswordHash('hash')
            ->setStatus('active');
        $em->persist($user);

        return $user;
    }

    private function makePublishedProperty(
        EntityManagerInterface $em,
        bool $instantBooking = true,
        int $maxGuests = 4,
        ?string $city = null,
        ?string $title = null,
    ): Property {
        $host = $this->makeUser($em, 'host-' . uniqid('', true) . '@test.local');
        $policy = (new CancellationPolicy())
            ->setCode('flex-' . uniqid('', true))
            ->setLabel('Flexible');
        $em->persist($policy);

        $property = (new Property())
            ->setHost($host)
            ->setCancellationPolicy($policy)
            ->setTitle($title ?? 'Logement de test workflow')
            ->setPropertyType('apartment')
            ->setStatus('published')
            ->setInstantBooking($instantBooking)
            ->setMaxGuests($maxGuests)
            ->setBedrooms(1)
            ->setBeds(1)
            ->setBathrooms(1)
            ->setPricePerNight('100.00');

        if ($city !== null) {
            $address = (new PropertyAddress())
                ->setCountry('France')
                ->setCity($city)
                ->setAddressLine1('1 rue de Test');
            $property->setAddress($address);
        }

        $em->persist($property);
        $em->flush();

        return $property;
    }

    private function makeReservation(
        EntityManagerInterface $em,
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        string $status,
        int $guests = 2,
    ): Reservation {
        $reservation = (new Reservation())
            ->setProperty($property)
            ->setGuest($guest)
            ->setCheckinDate($checkin)
            ->setCheckoutDate($checkout)
            ->setGuestsCount($guests)
            ->setStatus($status)
            ->setTotalPrice('100.00')
            ->setCurrency('EUR');

        if ($status === 'cancelled') {
            $reservation->setCancellationReason('Données de test');
        }

        $em->persist($reservation);
        $em->flush();

        return $reservation;
    }
}
