<?php

declare(strict_types=1);

namespace App\DataFixtures;

final class FixtureImageProvider
{
    private const string BASE_PARAMS = 'auto=format&fit=crop&w=800&q=80';

    /** @var array<string, list<string>> */
    private const array IMAGES_BY_TYPE = [
        'villa' => [
            'https://images.unsplash.com/photo-1613490493576-7fde63acd811?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?' . self::BASE_PARAMS,
        ],
        'loft' => [
            'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1493809842364-78817add7ffb?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1560448204-e02f11c45751?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?' . self::BASE_PARAMS,
        ],
        'apartment' => [
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1560185127-6ed189bf02f4?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1484154218962-a197022b5858?' . self::BASE_PARAMS,
        ],
        'house' => [
            'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1570129477492-45c003edd2be?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1605276374104-dee2a782edfc?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?' . self::BASE_PARAMS,
        ],
        'chalet' => [
            'https://images.unsplash.com/photo-1518780664697-55e3ad937233?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1542718610-a1d656d1884c?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1602343168117-bada8fa0fd90?' . self::BASE_PARAMS,
            'https://images.unsplash.com/photo-1600585154526-990dced4db0d?' . self::BASE_PARAMS,
        ],
    ];

    /**
     * @return list<string>
     */
    public static function forProperty(string $propertyType, string $title, int $count = 2): array
    {
        $pool = self::IMAGES_BY_TYPE[$propertyType] ?? self::IMAGES_BY_TYPE['house'];
        $offset = abs(crc32($title)) % count($pool);
        $images = [];

        for ($i = 0; $i < $count; $i++) {
            $images[] = $pool[($offset + $i) % count($pool)];
        }

        return $images;
    }

    public static function forReview(string $title): string
    {
        return self::forProperty('apartment', $title . '-review', 1)[0];
    }
}
