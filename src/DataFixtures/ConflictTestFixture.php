<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CancellationPolicy;
use App\Entity\Invoice;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyAmenity;
use App\Entity\PropertyAvailability;
use App\Entity\PropertyMedia;
use App\Entity\PropertyRule;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Security\Roles;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée 2 logements + 1 hôte + 2 voyageurs dédiés aux tests de conflits de réservation.
 *
 * === LOGEMENT A — test conflit 3h (instantBooking = true) ===
 * Réservation CONFIRMÉE : J+7 15:00 → J+10 11:00
 *
 *   ❌ Bloquer : J+10 12:00 → J+10 20:00  (gap = 1h, insuffisant)
 *   ❌ Bloquer : J+7  10:00 → J+7  16:00  (chevauchement direct)
 *   ✅ Passer  : J+10 14:00 → J+11 12:00  (gap = 3h pile)
 *   ✅ Passer  : J+12 10:00 → J+13 10:00  (créneau libre)
 *
 * === LOGEMENT B — test limite pending (instantBooking = false) ===
 * Réservations PENDING x2 : J+20 14:00 → J+23 11:00
 *
 *   ✅ Passer  : 3e pending sur ce créneau
 *   ❌ Bloquer : 4e pending sur ce créneau (max 3 atteint)
 */
class ConflictTestFixture extends Fixture implements DependentFixtureInterface
{
    public const PROPERTY_CONFLICT_A = 'property_conflict_a';
    public const PROPERTY_CONFLICT_B = 'property_conflict_b';
    public const USER_CONFLICT_HOST   = 'user_conflict_host';
    public const USER_CONFLICT_GUEST1 = 'user_conflict_guest1';
    public const USER_CONFLICT_GUEST2 = 'user_conflict_guest2';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        /** @var CancellationPolicy $policy */
        $policy = $this->getReference(FixtureReferences::POLICY_FLEXIBLE, CancellationPolicy::class);

        /* ─── Utilisateurs dédiés ─── */
        $host   = $this->makeUser('host.conflict@test.fr', 'Hôte', 'Conflit', [Roles::HOST], $manager);
        $guest1 = $this->makeUser('voyageur1@test.fr', 'Voyageur', 'Un', [], $manager);
        $guest2 = $this->makeUser('voyageur2@test.fr', 'Voyageur', 'Deux', [], $manager);

        $this->addReference(self::USER_CONFLICT_HOST,   $host);
        $this->addReference(self::USER_CONFLICT_GUEST1, $guest1);
        $this->addReference(self::USER_CONFLICT_GUEST2, $guest2);

        /* ─── Logement A — conflit + gap 3h ─── */
        $propA = $this->makeProperty(
            $host, $policy,
            '🔴 TEST CONFLITS — Villa Gap 3h',
            'Logement dédié au test de la règle du gap de 3h entre deux séjours.',
            'villa', 'France', 'Nice', '06000', 43.7102, 7.2620,
            '120.00', true, $manager,
        );
        $this->addReference(self::PROPERTY_CONFLICT_A, $propA);

        // Réservation confirmée J+7 15h → J+10 11h
        $checkin  = new \DateTimeImmutable('now midnight +7 days 15:00');
        $checkout = new \DateTimeImmutable('now midnight +10 days 11:00');
        $res = $this->makeReservation($propA, $guest1, $checkin, $checkout, 2, 'confirmed', '360.00', $manager, $host);
        $this->makeInvoice($res, 'CONF-A-001', '360.00', $manager);

        /* ─── Logement B — limite 3 pending ─── */
        $propB = $this->makeProperty(
            $host, $policy,
            '🟡 TEST PENDING MAX — Appartement Paris',
            'Logement dédié au test de la limite de 3 demandes en attente simultanées.',
            'apartment', 'France', 'Paris', '75001', 48.8600, 2.3470,
            '95.00', false, $manager,
        );
        $this->addReference(self::PROPERTY_CONFLICT_B, $propB);

        // 2 réservations pending J+20 14h → J+23 11h
        $p1 = new \DateTimeImmutable('now midnight +20 days 14:00');
        $p2 = new \DateTimeImmutable('now midnight +23 days 11:00');
        $this->makeReservation($propB, $guest1, $p1, $p2, 1, 'pending', '285.00', $manager, $guest1);
        $this->makeReservation($propB, $guest2, $p1, $p2, 1, 'pending', '285.00', $manager, $guest2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixture::class, CancellationPolicyFixture::class, AmenityFixture::class];
    }

    /* ─── helpers ─── */

    /** @param list<string> $roles */
    private function makeUser(string $email, string $first, string $last, array $roles, ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));
        $user->setPhone('+336' . random_int(10000000, 99999999));
        $user->setStatus('active');
        $user->setIsEmailVerified(true);
        $user->setPreferredLanguage('fr');
        $user->setPreferredCurrency('EUR');
        $user->setAssignedRoles($roles);

        $profile = new \App\Entity\UserProfile();
        $profile->setUser($user);
        $profile->setFirstName($first);
        $profile->setLastName($last);
        $profile->setIdentityStatus('verified');
        $manager->persist($profile);
        $user->setProfile($profile);

        $manager->persist($user);

        return $user;
    }

    private function makeProperty(
        User $host,
        CancellationPolicy $policy,
        string $title,
        string $description,
        string $type,
        string $country,
        string $city,
        string $postalCode,
        float $lat,
        float $lng,
        string $price,
        bool $instant,
        ObjectManager $manager,
    ): Property {
        $property = new Property();
        $property->setHost($host);
        $property->setCancellationPolicy($policy);
        $property->setTitle($title);
        $property->setDescription($description);
        $property->setPropertyType($type);
        $property->setStatus('published');
        $property->setMaxGuests(6);
        $property->setBedrooms(2);
        $property->setBeds(3);
        $property->setBathrooms(1);
        $property->setPricePerNight($price);
        $property->setCleaningFee('35.00');
        $property->setSecurityDeposit('100.00');
        $property->setCheckinTime(new \DateTimeImmutable('15:00'));
        $property->setCheckoutTime(new \DateTimeImmutable('11:00'));
        $property->setInstantBooking($instant);
        $manager->persist($property);

        $address = new PropertyAddress();
        $address->setCountry($country);
        $address->setCity($city);
        $address->setPostalCode($postalCode);
        $address->setAddressLine1('1 rue des Tests');
        $address->setLatitude((string) $lat);
        $address->setLongitude((string) $lng);
        $property->setAddress($address);

        $rules = new PropertyRule();
        $rules->setPetsAllowed(false);
        $rules->setSmokingAllowed(false);
        $rules->setPartiesAllowed(false);
        $rules->setAdditionalRules('Logement de test — règles standard.');
        $property->setRules($rules);

        foreach (FixtureImageProvider::forProperty($type, $title, 2) as $i => $url) {
            $media = new PropertyMedia();
            $media->setProperty($property);
            $media->setMediaType('image');
            $media->setFileUrl($url);
            $media->setSortOrder($i);
            $media->setIsCover($i === 0);
            $manager->persist($media);
        }

        for ($day = 0; $day < 60; $day++) {
            $avail = new PropertyAvailability();
            $avail->setProperty($property);
            $avail->setAvailableDate(new \DateTimeImmutable(sprintf('+%d days', $day)));
            $avail->setIsAvailable(true);
            $avail->setMinimumStay(1);
            $manager->persist($avail);
        }

        return $property;
    }

    private function makeReservation(
        Property $property,
        User $guest,
        \DateTimeImmutable $checkin,
        \DateTimeImmutable $checkout,
        int $guests,
        string $status,
        string $total,
        ObjectManager $manager,
        User $confirmedBy,
    ): Reservation {
        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate($checkin);
        $reservation->setCheckoutDate($checkout);
        $reservation->setGuestsCount($guests);
        $reservation->setStatus($status);
        $reservation->setTotalPrice($total);
        $reservation->setCleaningFee('35.00');
        $reservation->setServiceFee('25.00');
        $reservation->setSecurityDeposit('100.00');
        $reservation->setCurrency('EUR');
        $manager->persist($reservation);

        $h1 = new ReservationStatusHistory();
        $h1->setReservation($reservation);
        $h1->setOldStatus(null);
        $h1->setNewStatus('pending');
        $h1->setChangedBy($guest);
        $manager->persist($h1);

        if ($status !== 'pending') {
            $h2 = new ReservationStatusHistory();
            $h2->setReservation($reservation);
            $h2->setOldStatus('pending');
            $h2->setNewStatus($status);
            $h2->setChangedBy($confirmedBy);
            $manager->persist($h2);
        }

        return $reservation;
    }

    private function makeInvoice(Reservation $reservation, string $number, string $amount, ObjectManager $manager): void
    {
        $invoice = new Invoice();
        $invoice->setReservation($reservation);
        $invoice->setInvoiceNumber($number);
        $invoice->setPdfUrl('https://storage.example.com/invoices/' . md5($number) . '.pdf');
        $invoice->setTotalAmount($amount);
        $reservation->setInvoice($invoice);
        $manager->persist($invoice);
    }
}
