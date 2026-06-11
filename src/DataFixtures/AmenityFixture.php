<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Amenity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AmenityFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $amenities = [
            [FixtureReferences::AMENITY_WIFI, 'wifi', 'Wi-Fi', 'essentiel'],
            [FixtureReferences::AMENITY_POOL, 'pool', 'Piscine', 'exterieur'],
            [FixtureReferences::AMENITY_PARKING, 'parking', 'Parking gratuit', 'exterieur'],
            [FixtureReferences::AMENITY_KITCHEN, 'kitchen', 'Cuisine équipée', 'interieur'],
            [FixtureReferences::AMENITY_AC, 'ac', 'Climatisation', 'interieur'],
            [FixtureReferences::AMENITY_WASHER, 'washer', 'Lave-linge', 'interieur'],
            ['amenity_tv', 'tv', 'Télévision', 'interieur'],
            ['amenity_elevator', 'elevator', 'Ascenseur', 'accessibilite'],
            ['amenity_balcony', 'balcony', 'Balcon', 'exterieur'],
            ['amenity_fireplace', 'fireplace', 'Cheminée', 'interieur'],
        ];

        foreach ($amenities as [$reference, $code, $label, $category]) {
            $amenity = new Amenity();
            $amenity->setCode($code);
            $amenity->setLabel($label);
            $amenity->setCategory($category);
            $manager->persist($amenity);
            $this->addReference($reference, $amenity);
        }

        $manager->flush();
    }
}
