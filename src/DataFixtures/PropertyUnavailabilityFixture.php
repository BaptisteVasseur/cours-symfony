<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use App\Enum\UnavailabilityReason;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

class PropertyUnavailabilityFixture extends Fixture implements DependentFixtureInterface
{
    private readonly Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        $today = new \DateTimeImmutable('today');

        foreach ($manager->getRepository(Property::class)->findAll() as $property) {
            // Deux fenêtres lointaines et disjointes : elles ne chevauchent ni les réservations
            // confirmées seedées (≤ +24 jours) ni l'autre période bloquée du même logement.
            $windows = [
                [$this->faker->numberBetween(60, 80), $this->faker->numberBetween(2, 7)],
                [$this->faker->numberBetween(100, 130), $this->faker->numberBetween(2, 7)],
            ];

            foreach ($windows as [$startOffset, $nights]) {
                $start = $today->modify(sprintf('+%d days', $startOffset));

                $unavailability = new PropertyUnavailability();
                $unavailability->setProperty($property);
                $unavailability->setStartDate($start);
                $unavailability->setEndDate($start->modify(sprintf('+%d days', $nights)));
                $unavailability->setReason($this->faker->randomElement(UnavailabilityReason::cases()));
                $unavailability->setNote($this->faker->optional(0.6)->sentence());

                $manager->persist($unavailability);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PropertyFixture::class, ReservationFixture::class];
    }
}
