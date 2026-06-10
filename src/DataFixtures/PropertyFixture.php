<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Amenity;
use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyAmenity;
use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Entity\PropertyMedia;
use App\Entity\PropertyRule;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PropertyFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $hosts = [
            $this->getReference(FixtureReferences::USER_HOST_1, User::class),
            $this->getReference(FixtureReferences::USER_HOST_2, User::class),
        ];

        $policies = [
            $this->getReference(FixtureReferences::POLICY_FLEXIBLE, CancellationPolicy::class),
            $this->getReference(FixtureReferences::POLICY_MODERATE, CancellationPolicy::class),
            $this->getReference(FixtureReferences::POLICY_STRICT, CancellationPolicy::class),
        ];

        $amenityRefs = [
            FixtureReferences::AMENITY_WIFI,
            FixtureReferences::AMENITY_POOL,
            FixtureReferences::AMENITY_PARKING,
            FixtureReferences::AMENITY_KITCHEN,
            FixtureReferences::AMENITY_AC,
            FixtureReferences::AMENITY_WASHER,
        ];

        $properties = [
            [FixtureReferences::PROPERTY_1, 'Villa lumineuse avec piscine', 'Grande villa familiale avec jardin, terrasse et piscine chauffée près du centre.', 'villa', 'published', '220.00', 'Nice', 'France', '06000', 43.7102, 7.2620, 5],
            [FixtureReferences::PROPERTY_2, 'Loft design sur les quais', 'Loft spacieux avec vue sur la Garonne, cuisine équipée et espace bureau.', 'loft', 'published', '160.00', 'Bordeaux', 'France', '33000', 44.8378, -0.5792, 4],
            [FixtureReferences::PROPERTY_3, 'Appartement cosy Presqu’île', 'Appartement rénové au calme, idéal pour découvrir Lyon à pied en quelques jours.', 'apartment', 'published', '95.00', 'Lyon', 'France', '69002', 45.7640, 4.8357, 3],
            [null, 'Maison de pêcheur rénovée', 'Maison chaleureuse proche du port, parfaite pour un séjour en famille en Normandie.', 'house', 'published', '130.00', 'Honfleur', 'France', '14600', 49.4199, 0.2329, 2],
            [null, 'Chalet alpin familial', 'Chalet confortable avec cheminée, grand balcon et accès rapide aux pistes.', 'chalet', 'published', '210.00', 'Annecy', 'France', '74000', 45.8992, 6.1294, 5],
            [null, 'Studio calme près de la plage', 'Studio fonctionnel avec terrasse, à quelques minutes à pied du bassin.', 'apartment', 'pending', '75.00', 'Arcachon', 'France', '33120', 44.6614, -1.1722, 2],
            [null, 'Penthouse avec vue parisienne', 'Penthouse élégant avec terrasse panoramique et accès direct aux transports.', 'apartment', 'published', '340.00', 'Paris', 'France', '75009', 48.8566, 2.3522, 4],
            [null, 'Gîte provençal avec cour', 'Gîte authentique au calme avec cour ombragée et cuisine extérieure.', 'house', 'pending', '115.00', 'Aix-en-Provence', 'France', '13100', 43.5297, 5.4474, 3],
        ];

        foreach ($properties as $index => $data) {
            [$reference, $title, $description, $type, $status, $price, $city, $country, $postalCode, $latitude, $longitude, $imageCount] = $data;

            $property = $this->createProperty(
                $hosts[$index % count($hosts)],
                $policies[$index % count($policies)],
                $title,
                $description,
                $type,
                $status,
                $price,
                $city,
                $country,
                $postalCode,
                $latitude,
                $longitude,
                $amenityRefs,
                $manager,
                $index + 1,
                $imageCount,
                $index === 0,
            );

            $manager->persist($property);

            if (is_string($reference)) {
                $this->addReference($reference, $property);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixture::class, CancellationPolicyFixture::class, AmenityFixture::class];
    }

    private function createProperty(
        User $host,
        CancellationPolicy $policy,
        string $title,
        string $description,
        string $type,
        string $status,
        string $price,
        string $city,
        string $country,
        string $postalCode,
        float $latitude,
        float $longitude,
        array $amenityRefs,
        ObjectManager $manager,
        int $propertyNumber,
        int $imageCount,
        bool $withICal,
    ): Property {
        $property = new Property();
        $property->setHost($host);
        $property->setCancellationPolicy($policy);
        $property->setTitle($title);
        $property->setDescription($description);
        $property->setPropertyType($type);
        $property->setStatus($status);
        $property->setMaxGuests(random_int(2, 8));
        $property->setBedrooms(random_int(1, 4));
        $property->setBeds(random_int(1, 6));
        $property->setBathrooms(random_int(1, 3));
        $property->setPricePerNight($price);
        $property->setCleaningFee('45.00');
        $property->setSecurityDeposit('200.00');
        $property->setCheckinTime(new \DateTimeImmutable('15:00'));
        $property->setCheckoutTime(new \DateTimeImmutable('11:00'));
        $property->setInstantBooking($status === 'published');

        $address = new PropertyAddress();
        $address->setCountry($country);
        $address->setCity($city);
        $address->setPostalCode($postalCode);
        $address->setAddressLine1(sprintf('%d rue des Voyageurs', random_int(1, 120)));
        $address->setLatitude((string) $latitude);
        $address->setLongitude((string) $longitude);
        $property->setAddress($address);

        $rules = new PropertyRule();
        $rules->setPetsAllowed(random_int(0, 1) === 1);
        $rules->setSmokingAllowed(false);
        $rules->setPartiesAllowed(false);
        $rules->setAdditionalRules('Pas de fêtes après 22h. Respecter le voisinage.');
        $property->setRules($rules);

        foreach (array_slice($amenityRefs, 0, random_int(3, count($amenityRefs))) as $amenityRef) {
            $propertyAmenity = new PropertyAmenity();
            $propertyAmenity->setProperty($property);
            $propertyAmenity->setAmenity($this->getReference($amenityRef, Amenity::class));
            $manager->persist($propertyAmenity);
        }

        $images = FixtureImageProvider::forProperty($type, $title, $imageCount);
        foreach ($images as $i => $imageUrl) {
            $media = new PropertyMedia();
            $media->setProperty($property);
            $media->setMediaType('image');
            $media->setFileUrl($imageUrl);
            $media->setSortOrder($i);
            $media->setIsCover($i === 0);
            $manager->persist($media);
        }

        for ($day = 0; $day < 30; $day++) {
            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setAvailableDate(new \DateTimeImmutable(sprintf('+%d days', $day)));
            $availability->setIsAvailable($day % 7 !== 0);
            $availability->setPriceOverride($day % 5 === 0 ? (string) ((float) $price * 1.2) : null);
            $availability->setMinimumStay($day % 10 === 0 ? 3 : 1);
            $manager->persist($availability);
        }

        if ($withICal) {
            $iCalSync = new PropertyICalSync();
            $iCalSync->setProperty($property);
            $iCalSync->setProviderName('airbnb');
            $iCalSync->setICalUrl('https://calendar.example.com/ical/' . md5($title) . '.ics');
            $iCalSync->setLastSyncAt(new \DateTimeImmutable('-2 hours'));
            $manager->persist($iCalSync);
        }

        return $property;
    }
}
