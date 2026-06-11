<?php

namespace App\DataFixtures;

/**
 * Faker provider hachant un mot de passe en clair (bcrypt).
 * Le hash $2y$ reste vérifiable par le hasher Symfony "auto".
 */
final class PasswordProvider
{
    public function hashedPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }
}
