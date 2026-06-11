<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Property;
use App\Entity\Unavailability;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UnavailabilityFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $blocks = [
            [
                FixtureReferences::UNAVAILABILITY_PROPERTY_2,
                FixtureReferences::PROPERTY_2,
                '+45 days',
                '+50 days',
                'Travaux d\'entretien programmés',
            ],
            [
                FixtureReferences::UNAVAILABILITY_PROPERTY_3,
                FixtureReferences::PROPERTY_3,
                '+60 days',
                '+63 days',
                'Séjour personnel de l\'hôte',
            ],
        ];

        foreach ($blocks as [$reference, $propertyRef, $start, $end, $reason]) {
            $unavailability = new Unavailability();
            $unavailability->setProperty($this->getReference($propertyRef, Property::class));
            $unavailability->setStartDate(new \DateTimeImmutable($start));
            $unavailability->setEndDate(new \DateTimeImmutable($end));
            $unavailability->setReason($reason);
            $unavailability->setSource('manual');
            $manager->persist($unavailability);

            $this->addReference($reference, $unavailability);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PropertyFixture::class];
    }
}
