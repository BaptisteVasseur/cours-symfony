<?php

namespace App\DataFixtures;

use App\Entity\Reservation;
use App\Entity\Property;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReservationFixtures extends Fixture implements DependentFixtureInterface
{
    private const RESERVATIONS = [
        [
            'guest'        => 'user_guest_1',
            'property'     => 'property_0',
            'checkin'      => '2025-06-10',
            'checkout'     => '2025-06-15',
            'guests'       => 2,
            'status'       => 'completed',
            'totalPrice'   => '600.00',
            'cleaningFee'  => '25.00',
            'serviceFee'   => '45.00',
            'currency'     => 'EUR',
        ],
        [
            'guest'        => 'user_guest_1',
            'property'     => 'property_2',
            'checkin'      => '2025-12-20',
            'checkout'     => '2025-12-27',
            'guests'       => 4,
            'status'       => 'completed',
            'totalPrice'   => '1540.00',
            'cleaningFee'  => '50.00',
            'serviceFee'   => '110.00',
            'currency'     => 'EUR',
        ],
        [
            'guest'        => 'user_guest_1',
            'property'     => 'property_5',
            'checkin'      => '2026-03-01',
            'checkout'     => '2026-03-04',
            'guests'       => 1,
            'status'       => 'cancelled',
            'totalPrice'   => '285.00',
            'cleaningFee'  => '20.00',
            'serviceFee'   => '22.00',
            'currency'     => 'EUR',
            'cancellationReason' => 'Changement de programme',
        ],
        [
            'guest'        => 'user_guest_1',
            'property'     => 'property_7',
            'checkin'      => '2026-07-10',
            'checkout'     => '2026-07-17',
            'guests'       => 3,
            'status'       => 'confirmed',
            'totalPrice'   => '1960.00',
            'cleaningFee'  => '60.00',
            'serviceFee'   => '140.00',
            'currency'     => 'EUR',
        ],
        [
            'guest'        => 'user_guest_1',
            'property'     => 'property_3',
            'checkin'      => '2026-08-05',
            'checkout'     => '2026-08-07',
            'guests'       => 2,
            'status'       => 'pending',
            'totalPrice'   => '150.00',
            'cleaningFee'  => '15.00',
            'serviceFee'   => '12.00',
            'currency'     => 'EUR',
        ],
        // guest2
        [
            'guest'        => 'user_guest_2',
            'property'     => 'property_1',
            'checkin'      => '2025-08-01',
            'checkout'     => '2025-08-08',
            'guests'       => 6,
            'status'       => 'completed',
            'totalPrice'   => '2450.00',
            'cleaningFee'  => '80.00',
            'serviceFee'   => '175.00',
            'currency'     => 'EUR',
        ],
        [
            'guest'        => 'user_guest_2',
            'property'     => 'property_4',
            'checkin'      => '2026-05-01',
            'checkout'     => '2026-05-05',
            'guests'       => 4,
            'status'       => 'completed',
            'totalPrice'   => '640.00',
            'cleaningFee'  => '40.00',
            'serviceFee'   => '48.00',
            'currency'     => 'EUR',
        ],
        [
            'guest'        => 'user_guest_2',
            'property'     => 'property_6',
            'checkin'      => '2026-09-15',
            'checkout'     => '2026-09-18',
            'guests'       => 2,
            'status'       => 'confirmed',
            'totalPrice'   => '540.00',
            'cleaningFee'  => '30.00',
            'serviceFee'   => '40.00',
            'currency'     => 'EUR',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::RESERVATIONS as $i => $data) {
            /** @var User $guest */
            $guest = $this->getReference($data['guest'], User::class);
            /** @var Property $property */
            $property = $this->getReference($data['property'], Property::class);

            $reservation = new Reservation();
            $reservation->setGuest($guest);
            $reservation->setProperty($property);
            $reservation->setCheckinDate(new \DateTimeImmutable($data['checkin']));
            $reservation->setCheckoutDate(new \DateTimeImmutable($data['checkout']));
            $reservation->setGuestsCount($data['guests']);
            $reservation->setStatus($data['status']);
            $reservation->setTotalPrice($data['totalPrice']);
            $reservation->setCleaningFee($data['cleaningFee']);
            $reservation->setServiceFee($data['serviceFee']);
            $reservation->setCurrency($data['currency']);

            if (isset($data['cancellationReason'])) {
                $reservation->setCancellationReason($data['cancellationReason']);
            }

            $manager->persist($reservation);
            $this->addReference('reservation_' . $i, $reservation);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            PropertyFixtures::class,
        ];
    }
}