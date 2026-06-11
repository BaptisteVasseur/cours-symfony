<?php

namespace App\DataFixtures;

use App\Entity\AdminAction;
use App\Entity\Amenity;
use App\Entity\Booking;
use App\Entity\CancellationPolicy;
use App\Entity\Conversation;
use App\Entity\Dispute;
use App\Entity\Listing;
use App\Entity\ListingPhoto;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    private const PROPERTY_TYPES = ['apartment', 'house', 'villa', 'studio', 'loft', 'chalet', 'cottage'];
    private const BOOKING_STATUSES = ['pending', 'confirmed', 'cancelled', 'completed'];
    private const PAYMENT_METHODS = ['card', 'paypal', 'bank_transfer'];
    private const PAYMENT_STATUSES = ['pending', 'completed', 'refunded', 'failed'];
    private const DISPUTE_STATUSES = ['open', 'under_review', 'resolved', 'closed'];
    private const NOTIFICATION_TYPES = ['booking_request', 'booking_confirmed', 'booking_cancelled', 'new_message', 'new_review', 'payment_received'];
    private const CITIES = [
        ['city' => 'Paris', 'country' => 'France'],
        ['city' => 'Lyon', 'country' => 'France'],
        ['city' => 'Nice', 'country' => 'France'],
        ['city' => 'Bordeaux', 'country' => 'France'],
        ['city' => 'Marseille', 'country' => 'France'],
        ['city' => 'Toulouse', 'country' => 'France'],
        ['city' => 'Strasbourg', 'country' => 'France'],
        ['city' => 'Biarritz', 'country' => 'France'],
        ['city' => 'Barcelone', 'country' => 'Espagne'],
        ['city' => 'Rome', 'country' => 'Italie'],
    ];
    private const LISTING_TITLES = [
        'Charmant appartement en plein cœur de %s',
        'Studio moderne avec vue sur %s',
        'Maison de caractère à %s',
        'Loft design au cœur de %s',
        'Villa avec piscine près de %s',
        'Appartement lumineux avec terrasse à %s',
        'Chalet cosy aux portes de %s',
        'Duplex élégant à deux pas de %s',
        'Maison familiale spacieuse à %s',
        'Suite luxueuse en centre de %s',
    ];

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->seed(42);

        // --- Cancellation Policies ---
        $policies = $this->loadCancellationPolicies($manager);

        // --- Amenities ---
        $amenities = $this->loadAmenities($manager);

        // --- Users ---
        [$admin, $hosts, $guests] = $this->loadUsers($manager, $faker);

        // --- Listings ---
        $listings = $this->loadListings($manager, $faker, $hosts, $policies, $amenities);

        // --- Bookings, Payments, Reviews ---
        $bookings = $this->loadBookings($manager, $faker, $listings, $guests);

        // --- Conversations & Messages ---
        $this->loadConversations($manager, $faker, $listings, $guests, $hosts, $bookings);

        // --- Notifications ---
        $this->loadNotifications($manager, $faker, $guests, $hosts);

        // --- Admin Actions ---
        $this->loadAdminActions($manager, $faker, $admin, $listings, $guests);

        $manager->flush();
    }

    // -------------------------------------------------------------------------

    private function loadCancellationPolicies(ObjectManager $manager): array
    {
        $policies = [
            [
                'name' => 'Flexible',
                'description' => 'Remboursement complet jusqu\'à 24h avant l\'arrivée.',
                'rules' => ['100_percent_before' => 1, '0_percent_after' => 1],
            ],
            [
                'name' => 'Modérée',
                'description' => 'Remboursement complet jusqu\'à 5 jours avant l\'arrivée.',
                'rules' => ['100_percent_before' => 5, '50_percent_before' => 1],
            ],
            [
                'name' => 'Stricte',
                'description' => 'Remboursement de 50% jusqu\'à 7 jours avant l\'arrivée.',
                'rules' => ['50_percent_before' => 7, '0_percent_after' => 7],
            ],
        ];

        $result = [];
        foreach ($policies as $data) {
            $policy = new CancellationPolicy();
            $policy->setName($data['name']);
            $policy->setDescription($data['description']);
            $policy->setRefundRulesJson($data['rules']);
            $policy->setCreatedAt(new \DateTimeImmutable('-6 months'));
            $manager->persist($policy);
            $result[] = $policy;
        }

        return $result;
    }

    private function loadAmenities(ObjectManager $manager): array
    {
        $amenitiesData = [
            ['name' => 'Wi-Fi', 'icon' => 'wifi', 'category' => 'connectivity'],
            ['name' => 'Parking gratuit', 'icon' => 'parking', 'category' => 'transport'],
            ['name' => 'Piscine', 'icon' => 'pool', 'category' => 'outdoor'],
            ['name' => 'Climatisation', 'icon' => 'ac', 'category' => 'comfort'],
            ['name' => 'Cuisine équipée', 'icon' => 'kitchen', 'category' => 'kitchen'],
            ['name' => 'Lave-linge', 'icon' => 'washer', 'category' => 'appliances'],
            ['name' => 'Sèche-linge', 'icon' => 'dryer', 'category' => 'appliances'],
            ['name' => 'Télévision', 'icon' => 'tv', 'category' => 'entertainment'],
            ['name' => 'Cheminée', 'icon' => 'fireplace', 'category' => 'comfort'],
            ['name' => 'Jacuzzi', 'icon' => 'jacuzzi', 'category' => 'outdoor'],
            ['name' => 'Barbecue', 'icon' => 'bbq', 'category' => 'outdoor'],
            ['name' => 'Vélos disponibles', 'icon' => 'bike', 'category' => 'transport'],
            ['name' => 'Animaux acceptés', 'icon' => 'pet', 'category' => 'policy'],
            ['name' => 'Accès plage', 'icon' => 'beach', 'category' => 'outdoor'],
            ['name' => 'Espace de travail', 'icon' => 'workspace', 'category' => 'work'],
            ['name' => 'Petit-déjeuner inclus', 'icon' => 'breakfast', 'category' => 'food'],
        ];

        $amenities = [];
        foreach ($amenitiesData as $data) {
            $amenity = new Amenity();
            $amenity->setName($data['name']);
            $amenity->setIcon($data['icon']);
            $amenity->setCategory($data['category']);
            $amenity->setCreatedAt(new \DateTimeImmutable('-1 year'));
            $manager->persist($amenity);
            $amenities[] = $amenity;
        }

        return $amenities;
    }

    private function loadUsers(ObjectManager $manager, \Faker\Generator $faker): array
    {
        $password = password_hash('password123', PASSWORD_BCRYPT);

        // Admin
        $admin = $this->createUser($manager, $faker, 'admin@airbnb-clone.fr', $password, 'admin', 'Admin', 'Système');

        // Hosts
        $hostsData = [
            ['Marie', 'Dupont', 'marie.dupont@example.fr', 'France', 'Français'],
            ['Pierre', 'Martin', 'pierre.martin@example.fr', 'France', 'Français'],
            ['Sophie', 'Bernard', 'sophie.bernard@example.fr', 'France', 'Français, Anglais'],
            ['Lucas', 'Moreau', 'lucas.moreau@example.fr', 'France', 'Français'],
        ];

        $hosts = [];
        foreach ($hostsData as [$first, $last, $email, $country, $lang]) {
            $hosts[] = $this->createUser($manager, $faker, $email, $password, 'host', $first, $last, $country, $lang);
        }

        // Guests
        $guestsData = [
            ['Alice', 'Lefebvre', 'alice.lefebvre@example.fr'],
            ['Thomas', 'Leroy', 'thomas.leroy@example.fr'],
            ['Emma', 'Petit', 'emma.petit@example.fr'],
            ['Hugo', 'Robert', 'hugo.robert@example.fr'],
            ['Léa', 'Simon', 'lea.simon@example.fr'],
            ['Nathan', 'Laurent', 'nathan.laurent@example.fr'],
            ['Camille', 'Michel', 'camille.michel@example.fr'],
            ['Romain', 'Garcia', 'romain.garcia@example.fr'],
        ];

        $guests = [];
        foreach ($guestsData as [$first, $last, $email]) {
            $guests[] = $this->createUser($manager, $faker, $email, $password, 'guest', $first, $last);
        }

        return [$admin, $hosts, $guests];
    }

    private function createUser(
        ObjectManager $manager,
        \Faker\Generator $faker,
        string $email,
        string $password,
        string $role,
        string $firstName,
        string $lastName,
        string $country = 'France',
        string $language = 'Français'
    ): User {
        $now = new \DateTimeImmutable();
        $createdAt = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-2 years', '-6 months'));

        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash($password);
        $user->setRole($role);
        $user->setIsEmailVerified(true);
        $user->setCreatedAt($createdAt);
        $user->setUpdatedAt($now);

        $profile = new UserProfile();
        $profile->setFirstName($firstName);
        $profile->setLastName($lastName);
        $profile->setPhone($faker->phoneNumber());
        $profile->setAvatarUrl(sprintf('https://i.pravatar.cc/150?u=%s', urlencode($email)));
        $profile->setBio($faker->optional(0.7)->sentence(10));
        $profile->setIdentityVerified($faker->boolean(70));
        $profile->setCountry($country);
        $profile->setLanguage($language);
        $profile->setCreatedAt($createdAt);
        $profile->setUser($user);

        $manager->persist($user);
        $manager->persist($profile);

        return $user;
    }

    private function loadListings(
        ObjectManager $manager,
        \Faker\Generator $faker,
        array $hosts,
        array $policies,
        array $amenities
    ): array {
        $listings = [];
        $photoBaseUrls = [
            'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688',
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267',
            'https://images.unsplash.com/photo-1512917774080-9991f1c4c750',
            'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9',
            'https://images.unsplash.com/photo-1630699144867-37acec97df5a',
            'https://images.unsplash.com/photo-1568605114967-8130f3a36994',
            'https://images.unsplash.com/photo-1493809842364-78817add7ffb',
            'https://images.unsplash.com/photo-1554995207-c18c203602cb',
        ];

        $descriptions = [
            'Magnifique logement idéalement situé au cœur de la ville. Vous bénéficierez d\'un espace lumineux et entièrement équipé, parfait pour votre séjour.',
            'Profitez d\'un cadre exceptionnel dans ce bien de charme. Décoré avec soin, il vous offre tout le confort nécessaire pour un séjour inoubliable.',
            'Découvrez ce superbe espace de vie alliant modernité et confort. Parfaitement situé, il vous permettra de profiter pleinement de votre destination.',
            'Un havre de paix en plein cœur de la ville. Ce logement raffiné vous accueille dans une atmosphère chaleureuse et élégante.',
            'Idéal pour les voyageurs en quête d\'authenticité. Ce logement vous plongera dans l\'ambiance locale tout en vous garantissant un maximum de confort.',
        ];

        $listingCount = 0;
        foreach ($hosts as $host) {
            $nbListings = $faker->numberBetween(2, 4);
            for ($i = 0; $i < $nbListings; $i++) {
                $location = $faker->randomElement(self::CITIES);
                $propertyType = $faker->randomElement(self::PROPERTY_TYPES);
                $titleTemplate = self::LISTING_TITLES[$listingCount % count(self::LISTING_TITLES)];

                $listing = new Listing();
                $listing->setTitle(sprintf($titleTemplate, $location['city']));
                $listing->setDescription($faker->randomElement($descriptions));
                $listing->setPricePerNight((string) $faker->randomFloat(2, 50, 500));
                $listing->setMaxGuests($faker->numberBetween(1, 10));
                $listing->setBedrooms($faker->numberBetween(1, 5));
                $listing->setBathrooms((string) $faker->randomElement(['1', '1.5', '2', '2.5', '3']));
                $listing->setPropertyType($propertyType);
                $listing->setLatitude((string) $faker->latitude(41, 51));
                $listing->setLongitude((string) $faker->longitude(-5, 10));
                $listing->setCity($location['city']);
                $listing->setCountry($location['country']);
                $listing->setIsActive($faker->boolean(90));
                $listing->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-18 months', '-3 months')));
                $listing->setUpdatedAt(new \DateTimeImmutable());
                $listing->setHost($host);
                $listing->setCancellationPolicy($faker->randomElement($policies));

                // Photos (3 à 5 par annonce)
                $nbPhotos = $faker->numberBetween(3, 5);
                $shuffledPhotos = $faker->randomElements($photoBaseUrls, $nbPhotos);
                foreach ($shuffledPhotos as $pos => $photoUrl) {
                    $photo = new ListingPhoto();
                    $photo->setUrl($photoUrl . '?w=800&auto=format');
                    $photo->setPosition($pos);
                    $photo->setCreatedAt(new \DateTimeImmutable());
                    $photo->setListing($listing);
                    $manager->persist($photo);
                }

                // Amenities (4 à 10 par annonce)
                $nbAmenities = $faker->numberBetween(4, 10);
                $selectedAmenities = $faker->randomElements($amenities, $nbAmenities);
                foreach ($selectedAmenities as $amenity) {
                    $listing->addAmenity($amenity);
                }

                $manager->persist($listing);
                $listings[] = $listing;
                $listingCount++;
            }
        }

        return $listings;
    }

    private function loadBookings(
        ObjectManager $manager,
        \Faker\Generator $faker,
        array $listings,
        array $guests
    ): array {
        $bookings = [];

        foreach ($listings as $listing) {
            $nbBookings = $faker->numberBetween(1, 4);
            for ($i = 0; $i < $nbBookings; $i++) {
                $guest = $faker->randomElement($guests);
                $startDate = $faker->dateTimeBetween('-12 months', '+3 months');
                $nights = $faker->numberBetween(2, 14);
                $endDate = (clone $startDate)->modify("+{$nights} days");
                $pricePerNight = (float) $listing->getPricePerNight();
                $totalPrice = $pricePerNight * $nights;
                $platformFee = $totalPrice * 0.12;
                $hostAmount = $totalPrice - $platformFee;

                $isCompleted = $endDate < new \DateTime();
                $status = $isCompleted
                    ? $faker->randomElement(['completed', 'completed', 'completed', 'cancelled'])
                    : $faker->randomElement(['pending', 'confirmed', 'confirmed']);

                $createdAt = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-13 months', $startDate));

                $booking = new Booking();
                $booking->setGuest($guest);
                $booking->setListing($listing);
                $booking->setStartDate($startDate);
                $booking->setEndDate($endDate);
                $booking->setGuestsCount($faker->numberBetween(1, (int) $listing->getMaxGuests()));
                $booking->setTotalPrice((string) round($totalPrice, 2));
                $booking->setCurrency('EUR');
                $booking->setStatus($status);
                $booking->setSpecialRequests($faker->optional(0.3)->sentence(8));
                $booking->setCreatedAt($createdAt);
                $booking->setUpdatedAt(new \DateTimeImmutable());
                $manager->persist($booking);

                // Payment (pour les réservations confirmées ou complétées)
                if (in_array($status, ['confirmed', 'completed'])) {
                    $payment = new Payment();
                    $payment->setBooking($booking);
                    $payment->setAmount((string) round($totalPrice, 2));
                    $payment->setCurrency('EUR');
                    $payment->setPaymentMethod($faker->randomElement(self::PAYMENT_METHODS));
                    $payment->setStatus($status === 'completed' ? 'completed' : $faker->randomElement(['completed', 'pending']));
                    $payment->setPlatformFee((string) round($platformFee, 2));
                    $payment->setHostAmount((string) round($hostAmount, 2));
                    $payment->setTransactionId(strtoupper($faker->bothify('TXN-########-????')));
                    $payment->setCreatedAt($createdAt->modify('+1 hour'));
                    $manager->persist($payment);
                }

                // Review (uniquement pour les séjours terminés)
                if ($status === 'completed' && $faker->boolean(70)) {
                    $review = new Review();
                    $review->setBooking($booking);
                    $review->setReviewer($guest);
                    $review->setReviewee($listing->getHost());
                    $review->setListing($listing);
                    $review->setRating($faker->numberBetween(3, 5));
                    $review->setComment($faker->randomElement([
                        'Séjour parfait, logement exactement comme décrit. Hôte très accueillant, je recommande vivement !',
                        'Très bonne expérience. Le logement était propre et bien équipé. Emplacement idéal.',
                        'Magnifique logement avec une vue splendide. Tout était impeccable.',
                        'Hôte fantastique et logement superbe. On reviendra sans hésiter !',
                        'Très bon rapport qualité-prix. Logement confortable et bien situé.',
                        'Séjour agréable, logement propre. Quelques petites améliorations possibles mais dans l\'ensemble très bien.',
                        'Excellente communication avec l\'hôte. Le logement correspond parfaitement aux photos.',
                        'Un vrai coup de cœur ! Nous avons adoré ce logement et son ambiance unique.',
                    ]));
                    $review->setCreatedAt(\DateTimeImmutable::createFromMutable($endDate)->modify('+2 days'));
                    $manager->persist($review);
                }

                // Dispute (rare, uniquement sur réservations complétées/annulées)
                if (in_array($status, ['completed', 'cancelled']) && $faker->boolean(8)) {
                    $dispute = new Dispute();
                    $dispute->setBooking($booking);
                    $dispute->setRaisedBy($guest);
                    $dispute->setReason($faker->randomElement([
                        'Le logement ne correspondait pas à la description.',
                        'Problème de propreté constaté à l\'arrivée.',
                        'Équipements non fonctionnels signalés.',
                        'Remboursement non effectué suite à l\'annulation.',
                    ]));
                    $dispute->setDescription($faker->sentence(15));
                    $dispute->setStatus($faker->randomElement(self::DISPUTE_STATUSES));
                    $dispute->setResolution($faker->optional(0.5)->sentence(10));
                    $dispute->setCreatedAt(\DateTimeImmutable::createFromMutable($endDate)->modify('+3 days'));
                    $manager->persist($dispute);
                }

                $bookings[] = $booking;
            }
        }

        return $bookings;
    }

    private function loadConversations(
        ObjectManager $manager,
        \Faker\Generator $faker,
        array $listings,
        array $guests,
        array $hosts,
        array $bookings
    ): void {
        $sampleMessages = [
            'guest' => [
                'Bonjour, est-ce que le logement est disponible aux dates indiquées ?',
                'Pourriez-vous me confirmer l\'heure d\'arrivée possible ?',
                'Y a-t-il un parking à proximité ?',
                'Nous serons 4 personnes, est-ce que c\'est possible ?',
                'Le logement est-il adapté pour des enfants en bas âge ?',
                'Merci pour votre réponse ! Nous avons hâte d\'arriver.',
                'Parfait, nous confirmons la réservation.',
                'Bonjour, nous venons d\'arriver. Tout est parfait, merci !',
            ],
            'host' => [
                'Bonjour ! Oui, le logement est bien disponible à ces dates.',
                'L\'entrée est possible à partir de 15h. La clé se trouve dans la boîte à clé devant la porte.',
                'Il y a un parking gratuit juste en face de l\'immeuble.',
                'Bien sûr, le logement peut accueillir jusqu\'à 6 personnes.',
                'Absolument, le logement est très adapté pour les familles avec enfants.',
                'Super, j\'ai bien reçu votre demande. Je vous confirme la réservation.',
                'N\'hésitez pas si vous avez besoin de quoi que ce soit pendant votre séjour !',
                'Ravi que tout vous convienne ! Bon séjour parmi nous.',
            ],
        ];

        $selectedListings = $faker->randomElements($listings, min(8, count($listings)));
        foreach ($selectedListings as $listing) {
            $guest = $faker->randomElement($guests);
            $host = $listing->getHost();

            $relatedBooking = null;
            foreach ($bookings as $b) {
                if ($b->getListing() === $listing && $b->getGuest() === $guest) {
                    $relatedBooking = $b;
                    break;
                }
            }

            $conversation = new Conversation();
            $conversation->setListing($listing);
            $conversation->setBooking($relatedBooking);
            $conversation->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months', '-1 week')));
            $conversation->setUpdatedAt(new \DateTimeImmutable());
            $conversation->addParticipant($guest);
            $conversation->addParticipant($host);
            $manager->persist($conversation);

            $nbMessages = $faker->numberBetween(3, 8);
            $msgDate = $faker->dateTimeBetween('-6 months', '-1 week');

            for ($i = 0; $i < $nbMessages; $i++) {
                $isGuestTurn = ($i % 2 === 0);
                $sender = $isGuestTurn ? $guest : $host;
                $pool = $isGuestTurn ? $sampleMessages['guest'] : $sampleMessages['host'];

                $msgDate = (clone $msgDate)->modify('+' . $faker->numberBetween(5, 120) . ' minutes');

                $message = new Message();
                $message->setConversation($conversation);
                $message->setSender($sender);
                $message->setContent($faker->randomElement($pool));
                $message->setCreatedAt(\DateTimeImmutable::createFromMutable($msgDate));
                $manager->persist($message);
            }
        }
    }

    private function loadNotifications(
        ObjectManager $manager,
        \Faker\Generator $faker,
        array $guests,
        array $hosts
    ): void {
        $notifData = [
            'booking_request' => ['Nouvelle demande de réservation', 'Vous avez reçu une demande de réservation pour votre logement.'],
            'booking_confirmed' => ['Réservation confirmée !', 'Votre réservation a été confirmée par l\'hôte. Bon voyage !'],
            'booking_cancelled' => ['Réservation annulée', 'Votre réservation a été annulée. Un remboursement sera effectué sous 5 jours.'],
            'new_message' => ['Nouveau message', 'Vous avez reçu un nouveau message concernant votre séjour.'],
            'new_review' => ['Nouvel avis reçu', 'Un voyageur a laissé un avis sur votre logement. Consultez-le dès maintenant.'],
            'payment_received' => ['Paiement reçu', 'Le paiement de votre réservation a bien été traité.'],
        ];

        $allUsers = array_merge($guests, $hosts);
        foreach ($allUsers as $user) {
            $nbNotifs = $faker->numberBetween(2, 6);
            for ($i = 0; $i < $nbNotifs; $i++) {
                $type = $faker->randomElement(self::NOTIFICATION_TYPES);
                [$title, $content] = $notifData[$type];

                $notification = new Notification();
                $notification->setUser($user);
                $notification->setType($type);
                $notification->setTitle($title);
                $notification->setContent($content);
                $notification->setIsRead($faker->boolean(60));
                $notification->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-3 months', 'now')));
                $manager->persist($notification);
            }
        }
    }

    private function loadAdminActions(
        ObjectManager $manager,
        \Faker\Generator $faker,
        User $admin,
        array $listings,
        array $guests
    ): void {
        $actions = [
            ['action_type' => 'listing_suspended', 'target_type' => 'listing', 'description' => 'Annonce suspendue suite à un signalement de contenu inapproprié.'],
            ['action_type' => 'user_warned', 'target_type' => 'user', 'description' => 'Avertissement envoyé à l\'utilisateur pour violation des conditions d\'utilisation.'],
            ['action_type' => 'dispute_resolved', 'target_type' => 'user', 'description' => 'Litige résolu en faveur du voyageur après examen des preuves.'],
            ['action_type' => 'listing_approved', 'target_type' => 'listing', 'description' => 'Annonce approuvée après vérification manuelle des photos et de la description.'],
            ['action_type' => 'user_identity_verified', 'target_type' => 'user', 'description' => 'Identité de l\'utilisateur vérifiée manuellement par l\'équipe de support.'],
        ];

        for ($i = 0; $i < 6; $i++) {
            $actionData = $faker->randomElement($actions);
            $target = $actionData['target_type'] === 'listing'
                ? $faker->randomElement($listings)
                : $faker->randomElement($guests);

            $action = new AdminAction();
            $action->setAdmin($admin);
            $action->setActionType($actionData['action_type']);
            $action->setDescription($actionData['description']);
            $action->setTargetType($actionData['target_type']);
            $action->setTargetId($target->getId() ?? $faker->numberBetween(1, 100));
            $action->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months', 'now')));
            $manager->persist($action);
        }
    }
}
