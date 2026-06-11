<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Amenity;
use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyAmenity;
use App\Entity\PropertyMedia;
use App\Entity\PropertyRule;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            AmenityFixture::class,
            CancellationPolicyFixture::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var array<string, CancellationPolicy> $policies */
        $policies = [];
        foreach (['flexible', 'moderate', 'strict'] as $code) {
            $policy = $manager->getRepository(CancellationPolicy::class)->findOneBy(['code' => $code]);
            if ($policy !== null) {
                $policies[$code] = $policy;
            }
        }

        /** @var array<string, Amenity> $amenities */
        $amenities = [];
        foreach ($manager->getRepository(Amenity::class)->findAll() as $amenity) {
            $amenities[$amenity->getCode()] = $amenity;
        }

        $this->loadProperties($manager, $policies, $amenities);
        $manager->flush();
    }

    /** @param array<string, CancellationPolicy> $policies @param array<string, Amenity> $amenities */
    private function loadProperties(ObjectManager $manager, array $policies, array $amenities): void
    {
        $propertiesData = [
            [
                'host'        => 0,
                'title'       => 'Villa avec piscine - Côte d\'Azur',
                'description' => "Magnifique villa provençale avec piscine à débordement et vue mer panoramique.\nParfaite pour des vacances en famille ou entre amis dans un cadre exceptionnel.",
                'type'        => 'villa',
                'maxGuests'   => 8,
                'bedrooms'    => 4,
                'beds'        => 5,
                'bathrooms'   => 3,
                'price'       => '285.00',
                'cleaningFee' => '80.00',
                'deposit'     => '500.00',
                'checkin'     => '15:00',
                'checkout'    => '11:00',
                'instant'     => true,
                'policy'      => 'moderate',
                'city'        => 'Nice',
                'country'     => 'France',
                'postal'      => '06000',
                'address'     => '12 Avenue des Fleurs',
                'lat'         => '43.7102',
                'lng'         => '7.2620',
                'amenities'   => ['wifi', 'pool', 'parking', 'kitchen', 'ac'],
                'images'      => [
                    'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=800&q=80',
                    'https://images.unsplash.com/photo-1575517111839-3a3843ee7f5d?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => false,
                'partiesAllowed' => false,
            ],
            [
                'host'        => 0,
                'title'       => 'Appartement moderne centre-ville',
                'description' => "Superbe appartement design au cœur de Paris, à deux pas du Marais.\nToutes commodités, métro à 3 minutes à pied.",
                'type'        => 'apartment',
                'maxGuests'   => 4,
                'bedrooms'    => 2,
                'beds'        => 2,
                'bathrooms'   => 1,
                'price'       => '145.00',
                'cleaningFee' => '40.00',
                'deposit'     => '200.00',
                'checkin'     => '14:00',
                'checkout'    => '12:00',
                'instant'     => false,
                'policy'      => 'flexible',
                'city'        => 'Paris',
                'country'     => 'France',
                'postal'      => '75004',
                'address'     => '8 Rue des Rosiers',
                'lat'         => '48.8553',
                'lng'         => '2.3535',
                'amenities'   => ['wifi', 'kitchen', 'ac', 'washer'],
                'images'      => [
                    'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800&q=80',
                    'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => true,
                'partiesAllowed' => false,
            ],
            [
                'host'        => 5,
                'title'       => 'Chalet en bois - Alpes françaises',
                'description' => "Authentique chalet de montagne avec vue imprenable sur le Mont-Blanc.\nCheminée, spa privé et équipements ski inclus.",
                'type'        => 'chalet',
                'maxGuests'   => 10,
                'bedrooms'    => 5,
                'beds'        => 6,
                'bathrooms'   => 3,
                'price'       => '320.00',
                'cleaningFee' => '100.00',
                'deposit'     => '800.00',
                'checkin'     => '16:00',
                'checkout'    => '10:00',
                'instant'     => false,
                'policy'      => 'strict',
                'city'        => 'Chamonix',
                'country'     => 'France',
                'postal'      => '74400',
                'address'     => '3 Route du Mont-Blanc',
                'lat'         => '45.9237',
                'lng'         => '6.8694',
                'amenities'   => ['wifi', 'fireplace', 'parking', 'kitchen', 'tv'],
                'images'      => [
                    'https://images.unsplash.com/photo-1542718610-a1d656d1884c?w=800&q=80',
                    'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => true,
                'partiesAllowed' => false,
            ],
            [
                'host'        => 5,
                'title'       => 'Maison de pêcheur - Bretagne',
                'description' => "Charmante maison bretonne à 50 mètres de la plage, décoration bord de mer.\nParfaite pour découvrir les côtes sauvages et la gastronomie locale.",
                'type'        => 'house',
                'maxGuests'   => 6,
                'bedrooms'    => 3,
                'beds'        => 4,
                'bathrooms'   => 2,
                'price'       => '180.00',
                'cleaningFee' => '60.00',
                'deposit'     => '300.00',
                'checkin'     => '15:00',
                'checkout'    => '11:00',
                'instant'     => true,
                'policy'      => 'moderate',
                'city'        => 'Saint-Malo',
                'country'     => 'France',
                'postal'      => '35400',
                'address'     => '27 Rue du Port',
                'lat'         => '48.6493',
                'lng'         => '-2.0254',
                'amenities'   => ['wifi', 'parking', 'kitchen', 'washer', 'tv'],
                'images'      => [
                    'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800&q=80',
                    'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => true,
                'partiesAllowed' => true,
            ],
            [
                'host'        => 9,
                'title'       => 'Loft industriel avec balcon',
                'description' => "Loft atypique dans une ancienne usine rénovée, balcon privé de 40m².\nDécoration industrielle chic, idéal pour les voyageurs en quête d'originalité.",
                'type'        => 'loft',
                'maxGuests'   => 3,
                'bedrooms'    => 1,
                'beds'        => 2,
                'bathrooms'   => 1,
                'price'       => '120.00',
                'cleaningFee' => '35.00',
                'deposit'     => null,
                'checkin'     => '14:00',
                'checkout'    => '12:00',
                'instant'     => true,
                'policy'      => 'flexible',
                'city'        => 'Lyon',
                'country'     => 'France',
                'postal'      => '69003',
                'address'     => '15 Rue de la Soie',
                'lat'         => '45.7640',
                'lng'         => '4.8357',
                'amenities'   => ['wifi', 'balcony', 'kitchen', 'ac', 'tv'],
                'images'      => [
                    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800&q=80',
                    'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => false,
                'partiesAllowed' => false,
            ],
            [
                'host'        => 9,
                'title'       => 'Mas provençal - Luberon',
                'description' => "Mas authentique du 18ème siècle entièrement restauré au cœur du Luberon.\nPiscine, oliveraie, vue sur les Baux-de-Provence. Un havre de paix absolu.",
                'type'        => 'villa',
                'maxGuests'   => 12,
                'bedrooms'    => 6,
                'beds'        => 7,
                'bathrooms'   => 4,
                'price'       => '420.00',
                'cleaningFee' => '150.00',
                'deposit'     => '1000.00',
                'checkin'     => '17:00',
                'checkout'    => '10:00',
                'instant'     => false,
                'policy'      => 'strict',
                'city'        => 'Gordes',
                'country'     => 'France',
                'postal'      => '84220',
                'address'     => 'Route des Bories',
                'lat'         => '43.9116',
                'lng'         => '5.2012',
                'amenities'   => ['wifi', 'pool', 'parking', 'kitchen', 'ac', 'fireplace'],
                'images'      => [
                    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800&q=80',
                    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => false,
                'partiesAllowed' => false,
            ],
            [
                'host'        => 0,
                'title'       => 'Studio cosy - Quartier Marais',
                'description' => "Petit studio bien agencé dans le quartier le plus tendance de Paris.\nAccès immédiat aux musées, galeries et restaurants branchés.",
                'type'        => 'studio',
                'maxGuests'   => 2,
                'bedrooms'    => 1,
                'beds'        => 1,
                'bathrooms'   => 1,
                'price'       => '95.00',
                'cleaningFee' => '25.00',
                'deposit'     => null,
                'checkin'     => '15:00',
                'checkout'    => '11:00',
                'instant'     => true,
                'policy'      => 'flexible',
                'city'        => 'Paris',
                'country'     => 'France',
                'postal'      => '75003',
                'address'     => '22 Rue de Bretagne',
                'lat'         => '48.8632',
                'lng'         => '2.3606',
                'amenities'   => ['wifi', 'kitchen'],
                'images'      => [
                    'https://images.unsplash.com/photo-1501183638710-841dd1904471?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => false,
                'partiesAllowed' => false,
            ],
            [
                'host'        => 5,
                'title'       => 'Villa vue mer - Corse du Sud',
                'description' => "Villa contemporaine face à la mer avec accès direct à une plage privée.\nArchitecture moderne, matériaux nobles, confort 5 étoiles.",
                'type'        => 'villa',
                'maxGuests'   => 10,
                'bedrooms'    => 5,
                'beds'        => 6,
                'bathrooms'   => 4,
                'price'       => '550.00',
                'cleaningFee' => '180.00',
                'deposit'     => '1500.00',
                'checkin'     => '16:00',
                'checkout'    => '10:00',
                'instant'     => false,
                'policy'      => 'strict',
                'city'        => 'Bonifacio',
                'country'     => 'France',
                'postal'      => '20169',
                'address'     => 'Plage de Pianterella',
                'lat'         => '41.3882',
                'lng'         => '9.1628',
                'amenities'   => ['wifi', 'pool', 'parking', 'kitchen', 'ac', 'elevator'],
                'images'      => [
                    'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=800&q=80',
                    'https://images.unsplash.com/photo-1602343168117-bb8ffe3e2e9f?w=800&q=80',
                ],
                'smokingAllowed' => false,
                'petsAllowed'    => false,
                'partiesAllowed' => false,
            ],
        ];

        foreach ($propertiesData as $i => $data) {
            /** @var User $host */
            $host = $this->getReference('user_' . $data['host'], User::class);

            $property = new Property();
            $property->setHost($host);
            $property->setTitle($data['title']);
            $property->setDescription($data['description']);
            $property->setPropertyType($data['type']);
            $property->setStatus('published');
            $property->setMaxGuests($data['maxGuests']);
            $property->setBedrooms($data['bedrooms']);
            $property->setBeds($data['beds']);
            $property->setBathrooms($data['bathrooms']);
            $property->setPricePerNight($data['price']);
            $property->setCleaningFee($data['cleaningFee']);
            $property->setSecurityDeposit($data['deposit']);
            $property->setCheckinTime(new \DateTimeImmutable('2000-01-01 ' . $data['checkin']));
            $property->setCheckoutTime(new \DateTimeImmutable('2000-01-01 ' . $data['checkout']));
            $property->setInstantBooking($data['instant']);
            $property->setCancellationPolicy($policies[$data['policy']] ?? null);
            $property->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(10, 180))));

            $address = new PropertyAddress();
            $address->setCity($data['city']);
            $address->setCountry($data['country']);
            $address->setPostalCode($data['postal']);
            $address->setAddressLine1($data['address']);
            $address->setLatitude($data['lat']);
            $address->setLongitude($data['lng']);
            $property->setAddress($address);
            $manager->persist($address);

            $rules = new PropertyRule();
            $rules->setSmokingAllowed($data['smokingAllowed']);
            $rules->setPetsAllowed($data['petsAllowed']);
            $rules->setPartiesAllowed($data['partiesAllowed']);
            $property->setRules($rules);
            $manager->persist($rules);

            foreach ($data['images'] as $j => $url) {
                $media = new PropertyMedia();
                $media->setFileUrl($url);
                $media->setMediaType('image');
                $media->setSortOrder($j);
                $media->setIsCover($j === 0);
                $property->addMedium($media);
                $manager->persist($media);
            }

            foreach ($data['amenities'] as $code) {
                if (!isset($amenities[$code])) {
                    continue;
                }
                $propertyAmenity = new PropertyAmenity();
                $propertyAmenity->setProperty($property);
                $propertyAmenity->setAmenity($amenities[$code]);
                $manager->persist($propertyAmenity);
            }

            $manager->persist($property);

            $this->addReservations($manager, $property, $i);

            $this->addReference('app_property_' . $i, $property);
        }
    }

    private function addReservations(ObjectManager $manager, Property $property, int $propertyIndex): void
    {
        $reservationData = [
            ['guestRef' => 0, 'daysFromNow' => 10,  'nights' => 3, 'status' => 'confirmed'],
            ['guestRef' => 2, 'daysFromNow' => -20, 'nights' => 5, 'status' => 'completed'],
            ['guestRef' => 3, 'daysFromNow' => 30,  'nights' => 7, 'status' => 'pending'],
        ];

        $pick = $reservationData[$propertyIndex % count($reservationData)];

        $guestIndex = ($pick['guestRef'] + $propertyIndex) % 10;
        /** @var User $guest */
        $guest = $this->getReference('user_' . $guestIndex, User::class);

        if ($guest === $property->getHost()) {
            $guest = $this->getReference('user_' . (($guestIndex + 1) % 10), User::class);
        }

        $checkin  = new \DateTimeImmutable(($pick['daysFromNow'] >= 0 ? '+' : '') . $pick['daysFromNow'] . ' days');
        $checkout = $checkin->modify('+' . $pick['nights'] . ' days');

        $basePrice   = (float) $property->getPricePerNight() * $pick['nights'];
        $cleaningFee = (float) ($property->getCleaningFee() ?? 0);
        $serviceFee  = round($basePrice * 0.12, 2);
        $totalPrice  = $basePrice + $cleaningFee + $serviceFee;

        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount(random_int(1, $property->getMaxGuests()));
        $reservation->setStatus($pick['status']);
        $reservation->setTotalPrice((string) $totalPrice);
        $reservation->setCleaningFee($cleaningFee > 0 ? (string) $cleaningFee : null);
        $reservation->setServiceFee((string) $serviceFee);
        $reservation->setCurrency('EUR');
        $reservation->setCreatedAt(new \DateTimeImmutable('-' . random_int(1, 30) . ' days'));

        $manager->persist($reservation);

        if ($pick['status'] === 'completed') {
            $review = new Review();
            $review->setProperty($property);
            $review->setReservation($reservation);
            $review->setReviewer($guest);
            $review->setRating(random_int(4, 5));
            $review->setComment('Séjour excellent, propriété conforme aux photos. Hôte très accueillant. Je recommande vivement !');
            $review->setReviewedUser($property->getHost());
            $manager->persist($review);
        }
    }
}
