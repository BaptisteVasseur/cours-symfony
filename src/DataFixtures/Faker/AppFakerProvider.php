<?php

declare(strict_types=1);

namespace App\DataFixtures\Faker;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Formatters Faker custom exposés aux fixtures Alice (clé YAML : <methode(...)>).
 *
 * Ils couvrent ce que Faker ne sait pas faire nativement dans ce projet :
 *  - hacher un mot de passe (sinon le compte généré ne peut pas se connecter) ;
 *  - renvoyer un DateTimeImmutable (Faker renvoie un DateTime mutable, incompatible
 *    avec les setters typés \DateTimeImmutable des entités) ;
 *  - renvoyer un montant décimal en string (les setters DECIMAL sont typés string).
 */
#[AutoconfigureTag('nelmio_alice.faker.provider')]
final class AppFakerProvider
{
    public function __construct(
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    public function hashedPassword(string $plain = 'password'): string
    {
        return $this->hasherFactory->getPasswordHasher(User::class)->hash($plain);
    }

    /**
     * Accepte les formats relatifs de strtotime ("+1 days", "-60 years", "now").
     */
    public function dateImmutable(string $start = '-1 year', string $end = 'now'): \DateTimeImmutable
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        $timestamp = random_int(min($startTs, $endTs), max($startTs, $endTs));

        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }

    /**
     * Montant décimal "1234.56" (string, comme attendu par les colonnes DECIMAL).
     */
    public function amount(int $min, int $max): string
    {
        $cents = random_int($min * 100, $max * 100);

        return number_format($cents / 100, 2, '.', '');
    }
}
