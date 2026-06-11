<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Amenity;
use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyAmenity;
use App\Entity\PropertyAvailability;
use App\Entity\PropertyMedia;
use App\Entity\PropertyRule;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Security\Roles;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un hôte dédié (rôle ROLE_HOST uniquement) avec plusieurs logements,
 * des disponibilités, un blocage manuel et une réservation confirmée afin
 * d'illustrer l'espace hôte (/hote).
 *
 * Chargeable seule, sans purger la base :
 *   php bin/console doctrine:fixtures:load --append --group=host
 */
class HostAccountFixture extends Fixture implements FixtureGroupInterface
{
    private const string EMAIL = 'hote@example.com';
    private const string PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getGroups(): array
    {
        return ['host'];
    }

    public function load(ObjectManager $manager): void
    {
        // Idempotent : si l'hôte de démonstration existe déjà, on ne recrée rien.
        if ($manager->getRepository(User::class)->findOneBy(['email' => self::EMAIL]) !== null) {
            return;
        }

        // On réutilise une politique d'annulation et des équipements déjà présents en base.
        $policy = $manager->getRepository(CancellationPolicy::class)->findOneBy([]);
        if ($policy === null) {
            throw new \RuntimeException('Aucune politique d\'annulation en base. Chargez d\'abord les fixtures de base.');
        }

        /** @var list<Amenity> $amenities */
        $amenities = $manager->getRepository(Amenity::class)->findBy([], null, 4);

        $host = $this->createHost($manager);

        $villa = $this->createProperty(
            $host,
            $policy,
            $amenities,
            'Villa de l\'Hôte — Piscine privée',
            'Spacieuse villa avec piscine, idéale pour tester la gestion hôte.',
            'villa',
            '220.00',
            6,
            'Nice',
            'France',
            '06000',
            43.7102,
            7.2620,
            $manager,
        );

        $studio = $this->createProperty(
            $host,
            $policy,
            $amenities,
            'Studio de l\'Hôte — Cœur de ville',
            'Studio cosy en centre-ville, parfait pour deux voyageurs.',
            'apartment',
            '85.00',
            2,
            'Lyon',
            'France',
            '69002',
            45.7640,
            4.8357,
            $manager,
        );

        // Un voyageur existant pour créer une réservation confirmée sur la villa.
        $guest = $this->findGuest($manager, $host);
        if ($guest !== null) {
            $this->createConfirmedReservation($villa, $guest, $manager);
        }

        // Une période bloquée manuellement (travaux) sur le studio.
        $this->blockDates($studio, '+5 days', '+8 days', 'travaux', $manager);

        $manager->flush();
    }

    private function createHost(ObjectManager $manager): User
    {
        $host = new User();
        $host->setEmail(self::EMAIL);
        $host->setPasswordHash($this->passwordHasher->hashPassword($host, self::PASSWORD));
        $host->setPhone('+33606060606');
        $host->setStatus('active');
        $host->setIsEmailVerified(true);
        $host->setIs2faEnabled(false);
        $host->setPreferredLanguage('fr');
        $host->setPreferredCurrency('EUR');
        $host->setAssignedRoles([Roles::HOST]);
        $manager->persist($host);

        $profile = new UserProfile();
        $profile->setUser($host);
        $profile->setFirstName('Hugo');
        $profile->setLastName('Lhôte');
        $profile->setBirthDate(new \DateTimeImmutable('-38 years'));
        $profile->setAvatarUrl('https://i.pravatar.cc/150?u=' . self::EMAIL);
        $profile->setBio('Hôte de démonstration pour l\'espace de gestion.');
        $profile->setIdentityStatus('verified');
        $manager->persist($profile);
        $host->setProfile($profile);

        return $host;
    }

    /**
     * @param list<Amenity> $amenities
     */
    private function createProperty(
        User $host,
        CancellationPolicy $policy,
        array $amenities,
        string $title,
        string $description,
        string $type,
        string $price,
        int $maxGuests,
        string $city,
        string $country,
        string $postalCode,
        float $latitude,
        float $longitude,
        ObjectManager $manager,
    ): Property {
        $property = new Property();
        $property->setHost($host);
        $property->setCancellationPolicy($policy);
        $property->setTitle($title);
        $property->setDescription($description);
        $property->setPropertyType($type);
        $property->setStatus('published');
        $property->setMaxGuests($maxGuests);
        $property->setBedrooms($maxGuests > 2 ? 3 : 1);
        $property->setBeds($maxGuests > 2 ? 4 : 1);
        $property->setBathrooms($maxGuests > 2 ? 2 : 1);
        $property->setPricePerNight($price);
        $property->setCleaningFee('45.00');
        $property->setSecurityDeposit('200.00');
        $property->setCheckinTime(new \DateTimeImmutable('15:00'));
        $property->setCheckoutTime(new \DateTimeImmutable('11:00'));
        $property->setInstantBooking(true);
        $manager->persist($property);

        $address = new PropertyAddress();
        $address->setCountry($country);
        $address->setCity($city);
        $address->setPostalCode($postalCode);
        $address->setAddressLine1('1 rue de la Démo');
        $address->setLatitude((string) $latitude);
        $address->setLongitude((string) $longitude);
        $property->setAddress($address);

        $rules = new PropertyRule();
        $rules->setPetsAllowed(true);
        $rules->setSmokingAllowed(false);
        $rules->setPartiesAllowed(false);
        $rules->setAdditionalRules('Logement de démonstration — règles standard.');
        $property->setRules($rules);

        foreach ($amenities as $amenity) {
            $propertyAmenity = new PropertyAmenity();
            $propertyAmenity->setProperty($property);
            $propertyAmenity->setAmenity($amenity);
            $manager->persist($propertyAmenity);
        }

        $images = FixtureImageProvider::forProperty($type, $title, 2);
        foreach ($images as $index => $url) {
            $media = new PropertyMedia();
            $media->setProperty($property);
            $media->setMediaType('image');
            $media->setFileUrl($url);
            $media->setSortOrder($index);
            $media->setIsCover($index === 0);
            $manager->persist($media);
        }

        // Disponibilités ouvertes sur ~2 mois pour alimenter le calendrier.
        for ($day = 0; $day < 60; $day++) {
            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setAvailableDate(new \DateTimeImmutable(sprintf('+%d days', $day)));
            $availability->setIsAvailable(true);
            $availability->setMinimumStay(1);
            $property->addAvailability($availability);
            $manager->persist($availability);
        }

        return $property;
    }

    private function createConfirmedReservation(Property $property, User $guest, ObjectManager $manager): void
    {
        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate(new \DateTimeImmutable('+15 days'));
        $reservation->setCheckoutDate(new \DateTimeImmutable('+18 days'));
        $reservation->setGuestsCount(2);
        $reservation->setStatus('confirmed');
        $reservation->setTotalPrice('660.00');
        $reservation->setCleaningFee('45.00');
        $reservation->setServiceFee('45.00');
        $reservation->setSecurityDeposit('200.00');
        $reservation->setCurrency('EUR');
        $manager->persist($reservation);

        $created = new ReservationStatusHistory();
        $created->setReservation($reservation);
        $created->setOldStatus(null);
        $created->setNewStatus('pending');
        $created->setChangedBy($guest);
        $manager->persist($created);

        $confirmed = new ReservationStatusHistory();
        $confirmed->setReservation($reservation);
        $confirmed->setOldStatus('pending');
        $confirmed->setNewStatus('confirmed');
        $confirmed->setChangedBy($property->getHost());
        $manager->persist($confirmed);
    }

    private function blockDates(Property $property, string $start, string $end, string $reason, ObjectManager $manager): void
    {
        $current = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);

        // On bloque les disponibilités déjà créées sur la plage, sinon on en crée.
        while ($current <= $last) {
            $existing = null;
            foreach ($property->getAvailabilities() as $availability) {
                if ($availability->getAvailableDate()->format('Y-m-d') === $current->format('Y-m-d')) {
                    $existing = $availability;
                    break;
                }
            }

            if ($existing === null) {
                $existing = new PropertyAvailability();
                $existing->setProperty($property);
                $existing->setAvailableDate($current);
                $manager->persist($existing);
            }

            $existing->setIsAvailable(false);
            $existing->setBlockReason($reason);

            $current = $current->modify('+1 day');
        }
    }

    private function findGuest(ObjectManager $manager, User $host): ?User
    {
        $candidates = $manager->getRepository(User::class)->findBy([], null, 20);
        foreach ($candidates as $candidate) {
            if ($candidate === $host) {
                continue;
            }
            if (!in_array(Roles::HOST, $candidate->getAssignedRoles(), true)) {
                return $candidate;
            }
        }

        return null;
    }
}
