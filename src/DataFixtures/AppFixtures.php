<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Property;
use App\Entity\Booking;
use App\Entity\Review;
use App\Enum\BookingStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // --- Users ----------------------------------------------------------------
        $users = [];

        // Admin (optionnel)
        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setPassword('$2y$10$examplehashedpasswordexamplehashedpassw')
            ->setFirstname('Admin')
            ->setLastname('User')
            ->setPhone('0600000000')
            ->setRoles(['ROLE_ADMIN'])
            ->setCreatedAt(new \DateTimeImmutable('-120 days'))
            ->setIsVerified(true)
            ->setAdress('1 rue de l\'Admin')
            ->setCity('Paris')
            ->setPostalCode('75001');

        $manager->persist($admin);
        $users[] = $admin;

        // 20 utilisateurs lambda (certains joueront le rôle d’hôtes)
        for ($i = 1; $i <= 20; $i++) {
            $u = (new User())
                ->setEmail(sprintf('user%d@example.com', $i))
                ->setPassword('$2y$10$examplehashedpasswordexamplehashedpassw') // remplace par ton hash
                ->setFirstname($faker->firstName())
                ->setLastname($faker->lastName())
                ->setPhone($faker->phoneNumber())
                ->setRoles($faker->boolean(15) ? ['ROLE_HOST'] : [])
                ->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 year', 'now')))
                ->setIsVerified($faker->boolean(90))
                ->setAdress($faker->streetAddress())
                ->setCity($faker->city())
                ->setPostalCode($faker->postcode());

            $manager->persist($u);
            $users[] = $u;
        }

        // --- Properties -----------------------------------------------------------
        $properties = [];
        for ($i = 1; $i <= 35; $i++) {
            /** @var User $host */
            $host = $faker->randomElement($users);

            $price      = $faker->randomFloat(2, 35, 450);
            $bedrooms   = $faker->numberBetween(1, 5);
            $bathrooms  = $faker->numberBetween(1, 3);
            $maxGuests  = max(1, $bedrooms * $faker->numberBetween(1, 2));

            $p = (new Property())
                ->setTitle($faker->randomElement(['Studio', 'Appartement', 'Maison', 'Loft', 'Chalet']) . ' • ' .$faker->streetName())
                ->setDescription($faker->paragraphs(3, true))
                ->setAddress($faker->streetAddress())
                ->setCity($faker->city())
                ->setPricePerNight($price)
                ->setMaxGuests($maxGuests)
                ->setBedrooms($bedrooms)
                ->setBathrooms($bathrooms)
                ->setIsActive($faker->boolean(85))
                ->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-10 months', 'now')))
                ->setHost($host)
                ->setNote($faker->numberBetween(1, 5));

            $manager->persist($p);
            $properties[] = $p;
        }

        // --- Bookings -------------------------------------------------------------
        $bookings = [];

        $statuses = method_exists(BookingStatusEnum::class, 'cases')
            ? BookingStatusEnum::cases()
            : [BookingStatusEnum::PENDING];

        // On privilégie les propriétés actives
        $activeProps = array_values(array_filter($properties, fn(Property $p) => $p->isActive()));

        $bookingCount = 80;
        for ($i = 1; $i <= $bookingCount; $i++) {
            /** @var Property $property */
            $property = $faker->randomElement($activeProps ?: $properties);

            // guest != host
            do {
                /** @var User $guest */
                $guest = $faker->randomElement($users);
            } while ($property->getHost() && $guest === $property->getHost());

            // Fenêtre de réservation: passé récent -> futur proche
            $start  = $faker->dateTimeBetween('-6 months', '+3 months');
            $nights = $faker->numberBetween(1, 10);
            $end    = (clone $start)->modify("+$nights days");

            $b = (new Booking())
                ->setProperty($property)
                ->setGuest($guest)
                // Booking attend des \DateTime (mutables)
                ->setCheckIn(\DateTime::createFromImmutable(\DateTimeImmutable::createFromMutable($start)))
                ->setCheckOut(\DateTime::createFromImmutable(\DateTimeImmutable::createFromMutable($end)))
                ->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-8 months', 'now')))
                ->setStatus($faker->randomElement($statuses));

            $b->setTotalPrice($nights * (float) $property->getPricePerNight());

            $manager->persist($b);
            $bookings[] = $b;
        }

        // --- Reviews --------------------------------------------------------------
        // Crée des avis uniquement pour les bookings déjà terminés
        $now = new \DateTimeImmutable('now');

        foreach ($bookings as $b) {
            $checkOut = \DateTimeImmutable::createFromMutable($b->getCheckOut());
            if ($checkOut < $now && $faker->boolean(70)) { // 70% des séjours passés ont un avis
                $r = (new Review())
                    ->setRating($faker->numberBetween(1, 5))
                    ->setComment($faker->paragraphs(2, true))
                    // createdAt après la fin du séjour
                    ->setCreatedAt($faker->dateTimeBetween($checkOut->format('Y-m-d H:i:s'), 'now') instanceof \DateTime
                        ? \DateTimeImmutable::createFromMutable($faker->dateTimeBetween($checkOut->format('Y-m-d H:i:s'), 'now'))
                        : new \DateTimeImmutable('+1 day'))
                    ->setGuest($b->getGuest())
                    ->setProperty($b->getProperty());

                // Relation OneToOne : Review <-> Booking
                $r->setBooking($b);
                $b->setReview($r);

                $manager->persist($r);
            }
        }

        $manager->flush();
    }
}
