<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Amenity;
use App\Entity\CancellationPolicy;
use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Invoice;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\OauthAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\Payout;
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

class TestAccountFixture extends Fixture implements DependentFixtureInterface
{
    private const string EMAIL = 'test@example.com';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $testUser = $this->createTestUser($manager);
        $this->addReference(FixtureReferences::USER_TEST, $testUser);

        $guest1 = $this->getReference(FixtureReferences::USER_GUEST_1, User::class);
        $guest2 = $this->getReference(FixtureReferences::USER_GUEST_2, User::class);
        $host2 = $this->getReference(FixtureReferences::USER_HOST_2, User::class);
        $admin = $this->getReference(FixtureReferences::USER_ADMIN, User::class);
        $externalProperty = $this->getReference(FixtureReferences::PROPERTY_2, Property::class);
        $externalProperty2 = $this->getReference(FixtureReferences::PROPERTY_3, Property::class);

        $policy = $this->getReference(FixtureReferences::POLICY_MODERATE, CancellationPolicy::class);
        $amenityRefs = [
            FixtureReferences::AMENITY_WIFI,
            FixtureReferences::AMENITY_KITCHEN,
            FixtureReferences::AMENITY_AC,
            FixtureReferences::AMENITY_PARKING,
        ];

        $mainListing = $this->createProperty(
            $testUser,
            $policy,
            FixtureReferences::PROPERTY_TEST_MAIN,
            'Maison Test — Vue Mer',
            'Logement de démonstration avec terrasse et vue panoramique.',
            'house',
            'published',
            '175.00',
            'Biarritz',
            'France',
            '64200',
            43.4832,
            -1.5586,
            $amenityRefs,
            $manager,
        );
        $this->addReference(FixtureReferences::PROPERTY_TEST_MAIN, $mainListing);

        $secondListing = $this->createProperty(
            $testUser,
            $policy,
            FixtureReferences::PROPERTY_TEST_SECOND,
            'Appartement Test — Centre',
            'Appartement lumineux pour tester la gestion hôte.',
            'apartment',
            'published',
            '95.00',
            'Bordeaux',
            'France',
            '33000',
            44.8378,
            -0.5792,
            $amenityRefs,
            $manager,
        );
        $this->addReference(FixtureReferences::PROPERTY_TEST_SECOND, $secondListing);

        $pendingListing = $this->createProperty(
            $testUser,
            $policy,
            FixtureReferences::PROPERTY_TEST_PENDING,
            'Studio Test — En modération',
            'Annonce en attente de validation admin.',
            'apartment',
            'pending',
            '68.00',
            'Toulouse',
            'France',
            '31000',
            43.6047,
            1.4442,
            $amenityRefs,
            $manager,
        );
        $this->addReference(FixtureReferences::PROPERTY_TEST_PENDING, $pendingListing);

        $asGuestConfirmed = $this->createReservation(
            $externalProperty,
            $testUser,
            '+10 days',
            '+13 days',
            2,
            'confirmed',
            '885.00',
            $manager,
            $admin,
        );
        $this->addReference(FixtureReferences::RESERVATION_TEST_AS_GUEST, $asGuestConfirmed);

        $asGuestCompleted = $this->createReservation(
            $externalProperty2,
            $testUser,
            '-14 days',
            '-11 days',
            1,
            'completed',
            '312.00',
            $manager,
            $admin,
        );
        $this->addReference(FixtureReferences::RESERVATION_TEST_AS_GUEST_COMPLETED, $asGuestCompleted);

        $onListingConfirmed = $this->createReservation(
            $mainListing,
            $guest1,
            '+5 days',
            '+8 days',
            3,
            'confirmed',
            '570.00',
            $manager,
            $admin,
        );
        $this->addReference(FixtureReferences::RESERVATION_TEST_ON_LISTING, $onListingConfirmed);

        $onListingPending = $this->createReservation(
            $secondListing,
            $guest2,
            '+20 days',
            '+23 days',
            2,
            'pending',
            '330.00',
            $manager,
            $admin,
        );
        $this->addReference(FixtureReferences::RESERVATION_TEST_ON_LISTING_PENDING, $onListingPending);

        $this->createPayment($asGuestConfirmed, $testUser, '885.00', 'succeeded', '-2 days', $manager);
        $this->createPayment($asGuestCompleted, $testUser, '312.00', 'succeeded', '-12 days', $manager);
        $this->createPayment($onListingConfirmed, $guest1, '570.00', 'succeeded', '-1 day', $manager);

        $payout = new Payout();
        $payout->setHost($testUser);
        $payout->setReservation($onListingConfirmed);
        $payout->setAmount('456.00');
        $payout->setCurrency('EUR');
        $payout->setStatus('pending');
        $manager->persist($payout);

        $this->createConversation(
            $asGuestConfirmed,
            [$testUser, $host2],
            [
                [$testUser, 'Bonjour, puis-je arriver vers 17h ?'],
                [$host2, 'Bonjour ! Oui, aucun problème pour 17h.'],
                [$testUser, 'Super, merci !'],
            ],
            FixtureReferences::CONVERSATION_TEST,
            $manager,
        );

        $this->createConversation(
            $onListingConfirmed,
            [$guest1, $testUser],
            [
                [$guest1, 'Bonjour, le parking est-il inclus ?'],
                [$testUser, 'Oui, une place privée est disponible gratuitement.'],
                [$guest1, 'Parfait, à bientôt !'],
            ],
            null,
            $manager,
        );

        $notifications = [
            ['reservation_confirmed', 'Réservation confirmée', 'Votre séjour à Santorin est confirmé.', 'email', false],
            ['new_message', 'Nouveau message', 'Sophie vous a envoyé un message.', 'push', false],
            ['payment_received', 'Paiement reçu', 'Paiement de 570,00 € reçu pour votre annonce.', 'email', true],
            ['listing_pending', 'Annonce en attente', 'Votre studio à Toulouse est en cours de modération.', 'email', false],
            ['review_received', 'Nouvel avis', 'Sophie a laissé un avis 4/5 sur votre annonce.', 'push', true],
            ['booking_request', 'Demande de réservation', 'Lucas souhaite réserver votre appartement à Bordeaux.', 'email', false],
        ];

        foreach ($notifications as $index => [$type, $title, $content, $channel, $isRead]) {
            $notification = new Notification();
            $notification->setUser($testUser);
            $notification->setType($type);
            $notification->setTitle($title);
            $notification->setContent($content);
            $notification->setChannel($channel);
            $notification->setIsRead($isRead);
            $notification->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', $index + 1)));
            $manager->persist($notification);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            CancellationPolicyFixture::class,
            AmenityFixture::class,
            PropertyFixture::class,
        ];
    }

    private function createTestUser(ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail(self::EMAIL);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));
        $user->setPhone('+33601020304');
        $user->setStatus('active');
        $user->setIsEmailVerified(true);
        $user->setIs2faEnabled(false);
        $user->setPreferredLanguage('fr');
        $user->setPreferredCurrency('EUR');
        $user->setAssignedRoles([Roles::HOST, Roles::ADMIN]);
        $manager->persist($user);

        $profile = new UserProfile();
        $profile->setUser($user);
        $profile->setFirstName('Compte');
        $profile->setLastName('Test');
        $profile->setBirthDate(new \DateTimeImmutable('-32 years'));
        $profile->setAvatarUrl('https://i.pravatar.cc/150?u=test@example.com');
        $profile->setBio('Compte de démonstration avec propriétés, réservations, messages et paiements.');
        $profile->setIdentityStatus('verified');
        $manager->persist($profile);
        $user->setProfile($profile);

        $oauth = new OauthAccount();
        $oauth->setUser($user);
        $oauth->setProvider('google');
        $oauth->setProviderUserId('google_test_demo');
        $oauth->setAccessToken('access_token_test');
        $oauth->setRefreshToken('refresh_token_test');
        $manager->persist($oauth);

        foreach (['visa', 'mastercard'] as $index => $brand) {
            $paymentMethod = new PaymentMethod();
            $paymentMethod->setUser($user);
            $paymentMethod->setProvider('stripe');
            $paymentMethod->setProviderPaymentMethodId('pm_test_' . $index);
            $paymentMethod->setBrand($brand);
            $paymentMethod->setLast4((string) (4242 - $index));
            $paymentMethod->setExpirationMonth(12);
            $paymentMethod->setExpirationYear((int) date('Y') + 3);
            $manager->persist($paymentMethod);
        }

        return $user;
    }

    /**
     * @param list<string> $amenityRefs
     */
    private function createProperty(
        User $host,
        CancellationPolicy $policy,
        string $referenceKey,
        string $title,
        string $description,
        string $type,
        string $status,
        string $price,
        string $city,
        string $country,
        string $postalCode,
        float $latitude,
        float $longitude,
        array $amenityRefs,
        ObjectManager $manager,
    ): Property {
        $property = new Property();
        $property->setHost($host);
        $property->setCancellationPolicy($policy);
        $property->setTitle($title);
        $property->setDescription($description);
        $property->setPropertyType($type);
        $property->setStatus($status);
        $property->setMaxGuests(4);
        $property->setBedrooms(2);
        $property->setBeds(2);
        $property->setBathrooms(1);
        $property->setPricePerNight($price);
        $property->setCleaningFee('40.00');
        $property->setSecurityDeposit('150.00');
        $property->setCheckinTime(new \DateTimeImmutable('15:00'));
        $property->setCheckoutTime(new \DateTimeImmutable('11:00'));
        $property->setInstantBooking($status === 'published');
        $manager->persist($property);

        $address = new PropertyAddress();
        $address->setCountry($country);
        $address->setCity($city);
        $address->setPostalCode($postalCode);
        $address->setAddressLine1('12 avenue de Test');
        $address->setLatitude((string) $latitude);
        $address->setLongitude((string) $longitude);
        $property->setAddress($address);

        $rules = new PropertyRule();
        $rules->setPetsAllowed(true);
        $rules->setSmokingAllowed(false);
        $rules->setPartiesAllowed(false);
        $rules->setAdditionalRules('Compte de test — règles standard.');
        $property->setRules($rules);

        foreach ($amenityRefs as $amenityRef) {
            $propertyAmenity = new PropertyAmenity();
            $propertyAmenity->setProperty($property);
            $propertyAmenity->setAmenity($this->getReference($amenityRef, Amenity::class));
            $manager->persist($propertyAmenity);
        }

        $images = FixtureImageProvider::forProperty($type, $referenceKey, 2);
        foreach ($images as $index => $url) {
            $media = new PropertyMedia();
            $media->setProperty($property);
            $media->setMediaType('image');
            $media->setFileUrl($url);
            $media->setSortOrder($index);
            $media->setIsCover($index === 0);
            $manager->persist($media);
        }

        // Bloquer tous les 7 jours à titre d'exemple
        for ($day = 0; $day < 21; $day++) {
            if ($day % 7 === 0) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setBlockedDate(new \DateTimeImmutable(sprintf('+%d days', $day)));
                $manager->persist($availability);
            }
        }

        return $property;
    }

    private function createReservation(
        Property $property,
        User $guest,
        string $checkin,
        string $checkout,
        int $guestsCount,
        string $status,
        string $totalPrice,
        ObjectManager $manager,
        User $changedBy,
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
        $manager->persist($reservation);

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus(null);
        $history->setNewStatus('pending');
        $history->setChangedBy($guest);
        $manager->persist($history);

        if ($status !== 'pending') {
            $confirmed = new ReservationStatusHistory();
            $confirmed->setReservation($reservation);
            $confirmed->setOldStatus('pending');
            $confirmed->setNewStatus($status);
            $confirmed->setChangedBy($changedBy);
            $manager->persist($confirmed);
        }

        if (in_array($status, ['confirmed', 'completed'], true)) {
            static $invoiceCounter = 900;
            $invoice = new Invoice();
            $invoice->setReservation($reservation);
            $invoice->setInvoiceNumber(sprintf('INV-TEST-%05d', $invoiceCounter++));
            $invoice->setPdfUrl('https://storage.example.com/invoices/test-' . md5($totalPrice) . '.pdf');
            $invoice->setTotalAmount($totalPrice);
            $reservation->setInvoice($invoice);
            $manager->persist($invoice);
        }

        return $reservation;
    }

    private function createPayment(
        Reservation $reservation,
        User $payer,
        string $amount,
        string $status,
        string $paidAt,
        ObjectManager $manager,
    ): void {
        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setPayer($payer);
        $payment->setProvider('stripe');
        $payment->setProviderPaymentIntent('pi_test_' . md5($amount . $reservation->getId()));
        $payment->setAmount($amount);
        $payment->setCurrency('EUR');
        $payment->setStatus($status);
        $payment->setPaidAt(new \DateTimeImmutable($paidAt));
        $manager->persist($payment);
    }

    /**
     * @param list<User> $participants
     * @param list<array{0: User, 1: string}> $messages
     */
    private function createConversation(
        Reservation $reservation,
        array $participants,
        array $messages,
        ?string $reference,
        ObjectManager $manager,
    ): void {
        $conversation = new Conversation();
        $conversation->setReservation($reservation);
        $manager->persist($conversation);

        if ($reference !== null) {
            $this->addReference($reference, $conversation);
        }

        foreach ($participants as $participant) {
            $link = new ConversationParticipant();
            $link->setConversation($conversation);
            $link->setUser($participant);
            $manager->persist($link);
        }

        foreach ($messages as $index => [$sender, $content]) {
            $message = new Message();
            $message->setConversation($conversation);
            $message->setSender($sender);
            $message->setMessageType('text');
            $message->setContent($content);
            $message->setIsFlagged(false);
            $message->setCreatedAt(new \DateTimeImmutable(sprintf('-%d hours', count($messages) - $index)));
            $manager->persist($message);
        }
    }
}
