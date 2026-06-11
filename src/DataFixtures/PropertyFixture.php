<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Amenity;
use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyAmenity;
use App\Entity\AvailabilitySchedule;
use App\Entity\AvailabilityException;
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
            $this->getReference(FixtureReferences::USER_HOST_3, User::class),
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

        $featured = [
            [
                FixtureReferences::PROPERTY_1,
                'Villa Luxe avec Piscine Privée',
                'Magnifique villa avec vue montagne et piscine chauffée.',
                'villa',
                'pending',
                '145.00',
                'Chamonix',
                'France',
                '74400',
                46.2572,
                6.8012,
            ],
            [
                FixtureReferences::PROPERTY_2,
                'Loft Industriel Vue Mer',
                'Loft design face à la caldeira, idéal pour un séjour romantique.',
                'loft',
                'published',
                '280.00',
                'Santorin',
                'Grèce',
                '84700',
                36.3932,
                25.4615,
            ],
            [
                FixtureReferences::PROPERTY_3,
                'Appartement Cosy Centre-Ville',
                'Studio moderne à deux pas des commerces et transports.',
                'apartment',
                'published',
                '89.00',
                'Lyon',
                'France',
                '69002',
                45.7578,
                4.8320,
            ],
        ];

        foreach ($featured as $index => $data) {
            [$reference, $title, $description, $type, $status, $price, $city, $country, $postalCode, $lat, $lng] = $data;
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
                $lat,
                $lng,
                $amenityRefs,
                $manager,
                $index === 0
            );
            $manager->persist($property);
            $this->addReference($reference, $property);
        }

        $extraTitles = [
            ['Maison de Campagne Normande', 'house', 'published', '120.00', 'Deauville', 'France'],
            ['Chalet Alpin Familial', 'chalet', 'published', '195.00', 'Megève', 'France'],
            ['Studio Plage Bordeaux', 'apartment', 'draft', '75.00', 'Arcachon', 'France'],
            ['Penthouse Parisien', 'apartment', 'published', '350.00', 'Paris', 'France'],
            ['Gîte Rural Provence', 'house', 'published', '110.00', 'Aix-en-Provence', 'France'],
            ['Bungalow Tropical', 'house', 'pending', '160.00', 'Bali', 'Indonésie'],
        ];

        foreach ($extraTitles as $i => [$title, $type, $status, $price, $city, $country]) {
            $property = $this->createProperty(
                $hosts[$i % count($hosts)],
                $policies[$i % count($policies)],
                $title,
                'Description complète pour ' . $title,
                $type,
                $status,
                $price,
                $city,
                $country,
                sprintf('%05d', random_int(10000, 99999)),
                48.8566 + ($i * 0.1),
                2.3522 + ($i * 0.1),
                $amenityRefs,
                $manager,
                false
            );
            $manager->persist($property);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixture::class, CancellationPolicyFixture::class, AmenityFixture::class];
    }

    /**
     * @param list<string> $amenityRefs
     */
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
        $address->setAddressLine1(sprintf('%d rue de la Plage', random_int(1, 120)));
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

        $images = FixtureImageProvider::forProperty($type, $title);

        $cover = new PropertyMedia();
        $cover->setProperty($property);
        $cover->setMediaType('image');
        $cover->setFileUrl($images[0]);
        $cover->setSortOrder(0);
        $cover->setIsCover(true);
        $manager->persist($cover);

        $gallery = new PropertyMedia();
        $gallery->setProperty($property);
        $gallery->setMediaType('image');
        $gallery->setFileUrl($images[1]);
        $gallery->setSortOrder(1);
        $gallery->setIsCover(false);
        $manager->persist($gallery);

        // Définir des plages de disponibilité multiples, non chevauchantes
        $intervals = [
            ['today', '+9 days'],
            ['+11 days', '+20 days'],
            ['+23 days', '+35 days'],
            ['+38 days', '+60 days']
        ];

        foreach ($intervals as $index => [$startStr, $endStr]) {
            $startDate = new \DateTimeImmutable($startStr);
            $endDate = new \DateTimeImmutable($endStr);

            $schedule = new AvailabilitySchedule();
            $schedule->setProperty($property);
            $schedule->setStartDate($startDate);
            $schedule->setEndDate($endDate);

            // Aléatoire des jours d'ouverture de la plage
            $daysOption = random_int(1, 3);
            $days = match ($daysOption) {
                1 => [1, 2, 3, 4, 5, 6, 7], // Toute la semaine
                2 => [5, 6, 7],             // Weekend seulement
                3 => [1, 2, 3, 4, 5],       // Semaine seulement
            };
            $schedule->setDaysOfWeek($days);

            // Heures d'arrivée/départ aléatoires
            $checkInHour = ['14:00', '15:00', '16:00'][random_int(0, 2)];
            $checkOutHour = ['10:00', '11:00', '12:00'][random_int(0, 2)];
            $schedule->setCheckInTime(new \DateTimeImmutable($checkInHour));
            $schedule->setCheckOutTime(new \DateTimeImmutable($checkOutHour));

            // Durées de séjour aléatoires
            $minStay = random_int(1, 3);
            $maxStay = random_int(7, 30);
            $schedule->setMinimumStay($minStay);
            $schedule->setMaximumStay($maxStay);

            $manager->persist($schedule);

            // Créer une exception aléatoire au milieu de chaque plage
            $daysDiff = $startDate->diff($endDate)->days;
            if ($daysDiff > 2) {
                $randomOffset = random_int(1, $daysDiff - 1);
                $exceptionDate = $startDate->modify(sprintf('+%d days', $randomOffset));

                $exception = new AvailabilityException();
                $exception->setProperty($property);
                $exception->setDate($exceptionDate);
                
                $sourceOption = random_int(1, 2);
                if ($sourceOption === 1) {
                    $exception->setReason('Bloqué manuellement par l\'hôte');
                    $exception->setSource(AvailabilityException::SOURCE_MANUAL);
                } else {
                    $exception->setReason('Réservation externe importée via iCal');
                    $exception->setSource(AvailabilityException::SOURCE_ICAL_IMPORT);
                }
                
                $manager->persist($exception);
            }
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
