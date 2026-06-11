<?php

namespace App\DataFixtures;

/**
 * Faker provider : photos de logements libres de droit (Unsplash License).
 * URLs directes images.unsplash.com (pas de clé API requise).
 */
final class PropertyImageProvider
{
    private const IMAGE_URL = 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?q=80&w=1980&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D';

    public function propertyImage(int|string|null $index = null, int $width = 1024, int $height = 768): string
    {
        return self::IMAGE_URL;
    }
}
