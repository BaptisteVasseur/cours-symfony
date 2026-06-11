<?php

namespace App\DataFixtures;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PropertyFixtures extends Fixture implements DependentFixtureInterface
{
    private const PROPERTIES = [
        [
            'title'          => 'Appartement cosy au coeur de Paris',
            'description'    => 'Magnifique appartement haussmannien avec vue sur les toits.',
            'propertyType'   => 'apartment',
            'status'         => 'active',
            'maxGuests'      => 4,
            'bedrooms'       => 2,
            'beds'           => 3,
            'bathrooms'      => 1,
            'pricePerNight'  => '120.00',
            'cleaningFee'    => '25.00',
            'instantBooking' => true,
            'policy'         => 'policy_flexible',
            'address' => [
                'addressLine1' => '12 Rue de Rivoli',
                'city'         => 'Paris',
                'country'      => 'France',
                'postalCode'   => '75001',
            ],
        ],
        [
            'title'          => 'Villa avec piscine a Nice',
            'description'    => 'Villa provencale avec grande piscine et jardin.',
            'propertyType'   => 'villa',
            'status'         => 'active',
            'maxGuests'      => 8,
            'bedrooms'       => 4,
            'beds'           => 5,
            'bathrooms'      => 3,
            'pricePerNight'  => '350.00',
            'cleaningFee'    => '80.00',
            'instantBooking' => false,
            'policy'         => 'policy_strict',
            'address' => [
                'addressLine1' => '5 Avenue des Fleurs',
                'city'         => 'Nice',
                'country'      => 'France',
                'postalCode'   => '06000',
            ],
        ],
        [
            'title'          => 'Chalet montagne a Chamonix',
            'description'    => 'Chalet chaleureux au pied des pistes.',
            'propertyType'   => 'chalet',
            'status'         => 'active',
            'maxGuests'      => 6,
            'bedrooms'       => 3,
            'beds'           => 4,
            'bathrooms'      => 2,
            'pricePerNight'  => '220.00',
            'cleaningFee'    => '50.00',
            'instantBooking' => true,
            'policy'         => 'policy_moderate',
            'address' => [
                'addressLine1' => '8 Route du Mont-Blanc',
                'city'         => 'Chamonix',
                'country'      => 'France',
                'postalCode'   => '74400',
            ],
        ],
        [
            'title'          => 'Studio moderne a Lyon',
            'description'    => 'Studio refait a neuf dans le Vieux-Lyon.',
            'propertyType'   => 'studio',
            'status'         => 'active',
            'maxGuests'      => 2,
            'bedrooms'       => 1,
            'beds'           => 1,
            'bathrooms'      => 1,
            'pricePerNight'  => '75.00',
            'cleaningFee'    => '15.00',
            'instantBooking' => true,
            'policy'         => 'policy_flexible',
            'address' => [
                'addressLine1' => '3 Rue Saint-Jean',
                'city'         => 'Lyon',
                'country'      => 'France',
                'postalCode'   => '69005',
            ],
        ],
        [
            'title'          => 'Maison de charme en Bretagne',
            'description'    => 'Maison en pierre avec vue mer, ideale en famille.',
            'propertyType'   => 'house',
            'status'         => 'active',
            'maxGuests'      => 6,
            'bedrooms'       => 3,
            'beds'           => 4,
            'bathrooms'      => 2,
            'pricePerNight'  => '160.00',
            'cleaningFee'    => '40.00',
            'instantBooking' => false,
            'policy'         => 'policy_moderate',
            'address' => [
                'addressLine1' => '22 Rue du Port',
                'city'         => 'Saint-Malo',
                'country'      => 'France',
                'postalCode'   => '35400',
            ],
        ],
        [
            'title'          => 'Loft industriel a Bordeaux',
            'description'    => 'Grand loft dans une ancienne usine renovee.',
            'propertyType'   => 'loft',
            'status'         => 'active',
            'maxGuests'      => 3,
            'bedrooms'       => 1,
            'beds'           => 2,
            'bathrooms'      => 1,
            'pricePerNight'  => '95.00',
            'cleaningFee'    => '20.00',
            'instantBooking' => true,
            'policy'         => 'policy_flexible',
            'address' => [
                'addressLine1' => '47 Quai des Chartrons',
                'city'         => 'Bordeaux',
                'country'      => 'France',
                'postalCode'   => '33000',
            ],
        ],
        [
            'title'          => 'Cabane dans les arbres en Dordogne',
            'description'    => 'Experience unique a 8 metres de hauteur en pleine foret.',
            'propertyType'   => 'treehouse',
            'status'         => 'active',
            'maxGuests'      => 2,
            'bedrooms'       => 1,
            'beds'           => 1,
            'bathrooms'      => 1,
            'pricePerNight'  => '180.00',
            'cleaningFee'    => '30.00',
            'instantBooking' => false,
            'policy'         => 'policy_non_refundable',
            'address' => [
                'addressLine1' => 'Lieu-dit La Foret',
                'city'         => 'Sarlat-la-Caneda',
                'country'      => 'France',
                'postalCode'   => '24200',
            ],
        ],
        [
            'title'          => 'Penthouse vue mer a Marseille',
            'description'    => 'Penthouse luxueux avec terrasse panoramique sur la Mediterranee.',
            'propertyType'   => 'apartment',
            'status'         => 'active',
            'maxGuests'      => 5,
            'bedrooms'       => 2,
            'beds'           => 3,
            'bathrooms'      => 2,
            'pricePerNight'  => '280.00',
            'cleaningFee'    => '60.00',
            'instantBooking' => true,
            'policy'         => 'policy_strict',
            'address' => [
                'addressLine1' => '1 Corniche Kennedy',
                'city'         => 'Marseille',
                'country'      => 'France',
                'postalCode'   => '13007',
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        /** @var User $host */
        $host = $this->getReference('user_host', User::class);

        foreach (self::PROPERTIES as $i => $data) {
            /** @var CancellationPolicy $policy */
            $policy = $this->getReference($data['policy'], CancellationPolicy::class);

            $property = new Property();
            $property->setHost($host);
            $property->setCancellationPolicy($policy);
            $property->setTitle($data['title']);
            $property->setDescription($data['description']);
            $property->setPropertyType($data['propertyType']);
            $property->setStatus($data['status']);
            $property->setMaxGuests($data['maxGuests']);
            $property->setBedrooms($data['bedrooms']);
            $property->setBeds($data['beds']);
            $property->setBathrooms($data['bathrooms']);
            $property->setPricePerNight($data['pricePerNight']);
            $property->setCleaningFee($data['cleaningFee']);
            $property->setInstantBooking($data['instantBooking']);
            $property->setCheckinTime(new \DateTimeImmutable('14:00'));
            $property->setCheckoutTime(new \DateTimeImmutable('11:00'));

            $address = new PropertyAddress();
            $address->setAddressLine1($data['address']['addressLine1']);
            $address->setCity($data['address']['city']);
            $address->setCountry($data['address']['country']);
            $address->setPostalCode($data['address']['postalCode']);
            $property->setAddress($address);

            $manager->persist($property);
            $this->addReference('property_' . $i, $property);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CancellationPolicyFixtures::class,
        ];
    }
}
