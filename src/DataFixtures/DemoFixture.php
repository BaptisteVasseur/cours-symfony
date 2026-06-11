<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Amenity;
use App\Entity\CancellationPolicy;
use App\Entity\Invoice;
use App\Entity\Notification;
use App\Entity\PaymentMethod;
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
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures de démonstration — scénario minimal et lisible pour tests manuels.
 *
 * Comptes (mot de passe universel : "password") :
 *   admin@test.fr        — Admin
 *   host@test.fr         — Julien (hôte, ROLE_HOST)
 *   alice@test.fr        — Alice (voyageuse)
 *   bob@test.fr          — Bob (voyageur)
 *   charlie@test.fr      — Charlie (voyageur)
 *
 * Logements (tous chez Julien) :
 *   1. Appartement Paris Centre   — réservation instantanée (pas de confirmation)
 *   2. Villa Côte d'Azur          — réservation instantanée (pas de confirmation)
 *   3. Chalet Alpin               — confirmation requise (instantBooking=false)
 *   4. Maison de Campagne         — confirmation requise (instantBooking=false)
 *
 * Réservations :
 *   R1 — Alice  → Appartement Paris (confirmed, dans 10 j)
 *   R2 — Bob    → Villa Côte d'Azur (pending, dans 20 j) — en attente hôte
 *   R3 — Charlie → Chalet Alpin     (pending, dans 30 j) — en attente hôte
 *   R4 — Alice  → Maison Campagne   (completed, il y a 15 j)
 *   R5 — Bob    → Chalet Alpin      (cancelled, raison fournie)
 */
class DemoFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ------------------------------------------------------------------ users
        $admin   = $this->makeUser($manager, 'admin@test.fr',   'Admin',   'Plateforme', [Roles::ADMIN]);
        $host    = $this->makeUser($manager, 'host@test.fr',    'Julien',  'Dupré',      [Roles::HOST]);
        $alice   = $this->makeUser($manager, 'alice@test.fr',   'Alice',   'Martin',     []);
        $bob     = $this->makeUser($manager, 'bob@test.fr',     'Bob',     'Durand',     []);
        $charlie = $this->makeUser($manager, 'charlie@test.fr', 'Charlie', 'Bernard',    []);

        $this->addReference(FixtureReferences::USER_SUPER_ADMIN, $admin);
        $this->addReference(FixtureReferences::USER_HOST_1,      $host);
        $this->addReference(FixtureReferences::USER_GUEST_1,     $alice);
        $this->addReference(FixtureReferences::USER_GUEST_2,     $bob);
        $this->addReference(FixtureReferences::USER_GUEST_3,     $charlie);

        $manager->flush();

        // ------------------------------------------------------------------ properties
        $policy = $this->getReference(FixtureReferences::POLICY_MODERATE, CancellationPolicy::class);

        $amenityRefs = [
            FixtureReferences::AMENITY_WIFI,
            FixtureReferences::AMENITY_KITCHEN,
            FixtureReferences::AMENITY_AC,
            FixtureReferences::AMENITY_PARKING,
        ];

        $prop1 = $this->makeProperty(
            $manager, $host, $policy, $amenityRefs,
            FixtureReferences::PROPERTY_1,
            'Appartement Paris Centre',
            'Studio lumineux à deux pas des Champs-Élysées. Wifi haut débit, cuisine équipée, quartier animé.',
            'apartment', '95.00', 'Paris', '75008', 48.8738, 2.2950,
            instantBooking: true,
        );

        $prop2 = $this->makeProperty(
            $manager, $host, $policy, $amenityRefs,
            FixtureReferences::PROPERTY_2,
            'Villa Côte d\'Azur',
            'Grande villa avec piscine privée à 200 m de la plage de Cannes. 4 chambres, terrasse panoramique.',
            'villa', '280.00', 'Cannes', '06400', 43.5528, 7.0174,
            instantBooking: true,
        );

        $prop3 = $this->makeProperty(
            $manager, $host, $policy, $amenityRefs,
            FixtureReferences::PROPERTY_3,
            'Chalet Alpin',
            'Chalet authentique au pied des pistes de Chamonix. Bois brûlé, poêle à bois, vue Mont-Blanc.',
            'chalet', '195.00', 'Chamonix', '74400', 45.9237, 6.8694,
            instantBooking: false,
        );

        $prop4 = $this->makeProperty(
            $manager, $host, $policy, $amenityRefs,
            'property_4',
            'Maison de Campagne Normande',
            'Longère typique en pleine nature avec jardin, barbecue et terrain de pétanque. Idéal famille.',
            'house', '130.00', 'Deauville', '14800', 49.3597, 0.0751,
            instantBooking: false,
        );

        $manager->flush();

        // ------------------------------------------------------------------ reservations

        // R1 — Alice → Appartement Paris (confirmed, dans 10 j)
        $r1 = $this->makeReservation(
            $manager, $prop1, $alice, $admin,
            FixtureReferences::RESERVATION_CONFIRMED,
            '+10 days', '+14 days', 2, 'confirmed', '420.00',
        );

        // R2 — Bob → Villa Côte d'Azur (pending, dans 20 j) — hôte doit accepter
        $r2 = $this->makeReservation(
            $manager, $prop2, $bob, $bob,
            FixtureReferences::RESERVATION_PENDING,
            '+20 days', '+24 days', 3, 'pending', '1160.00',
        );

        // R3 — Charlie → Chalet Alpin (pending, dans 30 j) — hôte doit accepter
        $r3 = $this->makeReservation(
            $manager, $prop3, $charlie, $charlie,
            'reservation_charlie_chalet',
            '+30 days', '+35 days', 2, 'pending', '1015.00',
        );

        // R4 — Alice → Maison Campagne (completed, passée)
        $r4 = $this->makeReservation(
            $manager, $prop4, $alice, $admin,
            FixtureReferences::RESERVATION_COMPLETED,
            '-20 days', '-16 days', 4, 'completed', '560.00',
        );

        // R5 — Bob → Chalet Alpin (cancelled)
        $r5 = $this->makeReservation(
            $manager, $prop3, $bob, $admin,
            FixtureReferences::RESERVATION_CANCELLED,
            '+40 days', '+43 days', 2, 'cancelled', '625.00',
            'Changement de programme personnel.',
        );

        $manager->flush();

        // ------------------------------------------------------------------ notifications (hôte)
        foreach ([
            ['booking_requested', 'Nouvelle demande de réservation', 'Bob souhaite réserver la Villa Côte d\'Azur du 20 au 24.', false],
            ['booking_requested', 'Nouvelle demande de réservation', 'Charlie souhaite réserver le Chalet Alpin du 30 au 35.', false],
            ['booking_confirmed', 'Réservation confirmée', 'La réservation d\'Alice pour l\'Appartement Paris est confirmée.', true],
        ] as [$type, $title, $content, $isRead]) {
            $notif = new Notification();
            $notif->setUser($host);
            $notif->setType($type);
            $notif->setTitle($title);
            $notif->setContent($content);
            $notif->setChannel('email');
            $notif->setIsRead($isRead);
            $manager->persist($notif);
        }

        // notifications (alice)
        $notifAlice = new Notification();
        $notifAlice->setUser($alice);
        $notifAlice->setType('booking_confirmed');
        $notifAlice->setTitle('Réservation confirmée !');
        $notifAlice->setContent('Votre séjour à l\'Appartement Paris Centre est confirmé. Bon voyage !');
        $notifAlice->setChannel('email');
        $notifAlice->setIsRead(false);
        $manager->persist($notifAlice);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CancellationPolicyFixture::class, AmenityFixture::class];
    }

    // ------------------------------------------------------------------ helpers

    /** @param list<string> $roles */
    private function makeUser(
        ObjectManager $manager,
        string $email,
        string $firstName,
        string $lastName,
        array $roles,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));
        $user->setPhone('+336' . substr(md5($email), 0, 8));
        $user->setStatus('active');
        $user->setIsEmailVerified(true);
        $user->setIs2faEnabled(false);
        $user->setPreferredLanguage('fr');
        $user->setPreferredCurrency('EUR');
        $user->setAssignedRoles($roles);
        $manager->persist($user);

        $profile = new UserProfile();
        $profile->setUser($user);
        $profile->setFirstName($firstName);
        $profile->setLastName($lastName);
        $profile->setBirthDate(new \DateTimeImmutable('-30 years'));
        $profile->setAvatarUrl(sprintf('https://i.pravatar.cc/150?u=%s', urlencode($email)));
        $profile->setBio(sprintf('%s %s — compte de démonstration.', $firstName, $lastName));
        $profile->setIdentityStatus('verified');
        $manager->persist($profile);
        $user->setProfile($profile);

        $pm = new PaymentMethod();
        $pm->setUser($user);
        $pm->setProvider('stripe');
        $pm->setProviderPaymentMethodId('pm_demo_' . md5($email));
        $pm->setBrand('visa');
        $pm->setLast4('4242');
        $pm->setExpirationMonth(12);
        $pm->setExpirationYear((int) date('Y') + 3);
        $manager->persist($pm);

        return $user;
    }

    /** @param list<string> $amenityRefs */
    private function makeProperty(
        ObjectManager $manager,
        User $host,
        CancellationPolicy $policy,
        array $amenityRefs,
        string $reference,
        string $title,
        string $description,
        string $type,
        string $price,
        string $city,
        string $postalCode,
        float $lat,
        float $lng,
        bool $instantBooking,
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
        $property->setCleaningFee('40.00');
        $property->setSecurityDeposit('150.00');
        $property->setCheckinTime(new \DateTimeImmutable('15:00'));
        $property->setCheckoutTime(new \DateTimeImmutable('11:00'));
        $property->setInstantBooking($instantBooking);
        $manager->persist($property);

        $address = new PropertyAddress();
        $address->setCountry('France');
        $address->setCity($city);
        $address->setPostalCode($postalCode);
        $address->setAddressLine1('12 rue de la Paix');
        $address->setLatitude((string) $lat);
        $address->setLongitude((string) $lng);
        $property->setAddress($address);

        $rules = new PropertyRule();
        $rules->setPetsAllowed(false);
        $rules->setSmokingAllowed(false);
        $rules->setPartiesAllowed(false);
        $rules->setAdditionalRules('Respect du voisinage. Arrivée après 15h, départ avant 11h.');
        $property->setRules($rules);

        foreach ($amenityRefs as $ref) {
            $pa = new PropertyAmenity();
            $pa->setProperty($property);
            $pa->setAmenity($this->getReference($ref, Amenity::class));
            $manager->persist($pa);
        }

        $images = FixtureImageProvider::forProperty($type, $title, 2);
        foreach ($images as $i => $url) {
            $media = new PropertyMedia();
            $media->setProperty($property);
            $media->setMediaType('image');
            $media->setFileUrl($url);
            $media->setSortOrder($i);
            $media->setIsCover($i === 0);
            $manager->persist($media);
        }

        // Disponibilités : 90 jours, tout disponible sauf dimanches
        for ($day = 0; $day < 90; $day++) {
            $date = new \DateTimeImmutable(sprintf('+%d days', $day));
            $avail = new PropertyAvailability();
            $avail->setProperty($property);
            $avail->setAvailableDate($date);
            $avail->setIsAvailable((int) $date->format('N') !== 7);
            $avail->setMinimumStay(1);
            $manager->persist($avail);
        }

        $this->addReference($reference, $property);

        return $property;
    }

    private function makeReservation(
        ObjectManager $manager,
        Property $property,
        User $guest,
        User $changedBy,
        string $reference,
        string $checkin,
        string $checkout,
        int $guestsCount,
        string $status,
        string $totalPrice,
        ?string $cancellationReason = null,
    ): Reservation {
        $reservation = new Reservation();
        $reservation->setProperty($property);
        $reservation->setGuest($guest);
        $reservation->setCheckinDate(new \DateTimeImmutable($checkin));
        $reservation->setCheckoutDate(new \DateTimeImmutable($checkout));
        $reservation->setGuestsCount($guestsCount);
        $reservation->setStatus($status);
        $reservation->setTotalPrice($totalPrice);
        $reservation->setCleaningFee('40.00');
        $reservation->setServiceFee('30.00');
        $reservation->setSecurityDeposit('150.00');
        $reservation->setCurrency('EUR');
        $reservation->setCancellationReason($cancellationReason);
        $manager->persist($reservation);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus(null);
        $history->setNewStatus('pending');
        $history->setChangedBy($guest);
        $manager->persist($history);

        if ($status !== 'pending') {
            $next = new ReservationStatusHistory();
            $next->setReservation($reservation);
            $next->setOldStatus('pending');
            $next->setNewStatus($status);
            $next->setChangedBy($changedBy);
            $manager->persist($next);
        }

        if (in_array($status, ['confirmed', 'completed'], true)) {
            static $counter = 1;
            $invoice = new Invoice();
            $invoice->setReservation($reservation);
            $invoice->setInvoiceNumber(sprintf('INV-DEMO-%05d', $counter++));
            $invoice->setPdfUrl('https://storage.example.com/invoices/demo-' . md5($reference) . '.pdf');
            $invoice->setTotalAmount($totalPrice);
            $reservation->setInvoice($invoice);
            $manager->persist($invoice);
        }

        $this->addReference($reference, $reservation);

        return $reservation;
    }
}
