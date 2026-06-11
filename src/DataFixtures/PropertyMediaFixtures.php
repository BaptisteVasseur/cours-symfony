<?php

namespace App\DataFixtures;

use App\Entity\PropertyMedia;
use App\Entity\Property;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PropertyMediaFixtures extends Fixture implements DependentFixtureInterface
{
    // 5 images Unsplash par logement, première = cover
    private const MEDIA = [
        // property_0 - Appartement Paris
        0 => [
            'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800&q=80',
            'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&q=80',
            'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80',
            'https://images.unsplash.com/photo-1484154218962-a197022b5858?w=800&q=80',
            'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?w=800&q=80',
        ],
        // property_1 - Villa Nice
        1 => [
            'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=800&q=80',
            'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?w=800&q=80',
            'https://images.unsplash.com/photo-1575517111839-3a3843ee7f5d?w=800&q=80',
            'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&q=80',
            'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
        ],
        // property_2 - Chalet Chamonix
        2 => [
            'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=800&q=80',
            'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',
            'https://images.unsplash.com/photo-1530521954074-e64f6810b32d?w=800&q=80',
            'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&q=80',
            'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
        ],
        // property_3 - Studio Lyon
        3 => [
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800&q=80',
            'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
            'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80',
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800&q=80',
            'https://images.unsplash.com/photo-1519643381401-22c77e60520e?w=800&q=80',
        ],
        // property_4 - Maison Bretagne
        4 => [
            'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=800&q=80',
            'https://images.unsplash.com/photo-1505843513577-22bb7d21e455?w=800&q=80',
            'https://images.unsplash.com/photo-1449844908441-8829872d2607?w=800&q=80',
            'https://images.unsplash.com/photo-1416331108676-a22ccb276e35?w=800&q=80',
            'https://images.unsplash.com/photo-1510798831971-661eb04b3739?w=800&q=80',
        ],
        // property_5 - Loft Bordeaux
        5 => [
            'https://images.unsplash.com/photo-1536376072261-38c75010e6c9?w=800&q=80',
            'https://images.unsplash.com/photo-1560185008-b033106af5c3?w=800&q=80',
            'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800&q=80',
            'https://images.unsplash.com/photo-1600210492493-0946911123ea?w=800&q=80',
            'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
        ],
        // property_6 - Cabane Dordogne
        6 => [
            'https://images.unsplash.com/photo-1499696010180-025ef6e1a8f9?w=800&q=80',
            'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=800&q=80',
            'https://images.unsplash.com/photo-1448375240586-882707db888b?w=800&q=80',
            'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=800&q=80',
            'https://images.unsplash.com/photo-1542718610-a1d656d1884c?w=800&q=80',
        ],
        // property_7 - Penthouse Marseille
        7 => [
            'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=800&q=80',
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
            'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800&q=80',
            'https://images.unsplash.com/photo-1600573472592-401b489a3cdc?w=800&q=80',
            'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?w=800&q=80',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::MEDIA as $propertyIndex => $urls) {
            /** @var Property $property */
            $property = $this->getReference('property_' . $propertyIndex, Property::class);

            foreach ($urls as $order => $url) {
                $media = new PropertyMedia();
                $media->setProperty($property);
                $media->setFileUrl($url);
                $media->setMediaType('image');
                $media->setSortOrder($order);
                $media->setIsCover($order === 0); // première image = cover

                $manager->persist($media);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            PropertyFixtures::class,
        ];
    }
}