<?php

namespace App\DataFixtures;

use App\Entity\Amenity;
use App\Entity\Availability;
use App\Entity\Badge;
use App\Entity\Booking;
use App\Entity\Challenge;
use App\Entity\GamificationUserStats;
use App\Entity\Message;
use App\Entity\Payment;
use App\Entity\Property;
use App\Entity\PropertyPhoto;
use App\Entity\Review;
use App\Entity\Reward;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\UserChallenge;
use App\Entity\UserReward;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = $this->createUsers($manager);
        $amenities = $this->createAmenities($manager);
        $properties = $this->createProperties($manager, $users, $amenities);
        $bookings = $this->createBookings($manager, $properties, $users);
        $this->createPayments($manager, $bookings);
        $this->createReviews($manager, $bookings, $users);
        $this->createMessages($manager, $bookings, $users);
        $badges = $this->createBadges($manager);
        $this->createUserBadges($manager, $users, $badges);
        $challenges = $this->createChallenges($manager);
        $this->createUserChallenges($manager, $users, $challenges);
        $rewards = $this->createRewards($manager);
        $this->createUserRewards($manager, $users, $rewards);
        $this->createGamificationStats($manager, $users);

        $manager->flush();
    }

    private function createUsers(ObjectManager $manager): array
    {
        $users = [];
        $firstNames = ['Jean', 'Marie', 'Pierre', 'Sophie', 'Thomas', 'Emma', 'Lucas', 'Léa', 'Hugo', 'Chloé', 'Louis', 'Manon', 'Alexandre', 'Camille', 'Antoine'];
        $lastNames = ['Dupont', 'Martin', 'Bernard', 'Dubois', 'Moreau', 'Laurent', 'Simon', 'Michel', 'Lefebvre', 'Leroy', 'Roux', 'David', 'Bertrand', 'Morel', 'Fournier'];
        $cities = ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier', 'Bordeaux', 'Lille'];
        $countries = ['France', 'Espagne', 'Italie', 'Allemagne', 'Portugal'];

        for ($i = 0; $i < 20; $i++) {
            $user = new User();
            $user->setEmail('user' . $i . '@example.com');
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password123'));
            $user->setFirstName($firstNames[array_rand($firstNames)]);
            $user->setLastName($lastNames[array_rand($lastNames)]);
            $user->setPhone('+33' . rand(100000000, 999999999));
            $user->setProfilePictureUrl('https://i.pravatar.cc/150?img=' . ($i + 1));
            $user->setBio('Voyageur passionné depuis ' . rand(1, 10) . ' ans. J\'adore découvrir de nouveaux endroits et rencontrer des gens.');
            $user->setLanguage(['fr', 'en', 'es'][rand(0, 2)]);
            $user->setCurrency(['EUR', 'USD', 'GBP'][rand(0, 2)]);
            $user->setCreatedAt(new \DateTimeImmutable('-' . rand(30, 365) . ' days'));
            $user->setUpdatedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));

            $manager->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    private function createAmenities(ObjectManager $manager): array
    {
        $amenitiesData = [
            ['WiFi', 'wifi', 'Essentiels'],
            ['Cuisine', 'kitchen', 'Essentiels'],
            ['Lave-linge', 'washer', 'Essentiels'],
            ['Sèche-linge', 'dryer', 'Essentiels'],
            ['Climatisation', 'ac', 'Confort'],
            ['Chauffage', 'heating', 'Confort'],
            ['Télévision', 'tv', 'Divertissement'],
            ['Parking', 'parking', 'Localisation'],
            ['Piscine', 'pool', 'Équipements'],
            ['Jacuzzi', 'hot-tub', 'Équipements'],
            ['Salle de sport', 'gym', 'Équipements'],
            ['Cheminée', 'fireplace', 'Confort'],
            ['Balcon', 'balcony', 'Localisation'],
            ['Jardin', 'garden', 'Localisation'],
            ['Animaux acceptés', 'pets', 'Règles'],
            ['Fumeur accepté', 'smoking', 'Règles'],
            ['Accessible fauteuil roulant', 'accessible', 'Accessibilité'],
            ['Ascenseur', 'elevator', 'Accessibilité'],
            ['Petit-déjeuner', 'breakfast', 'Services'],
            ['Conciergerie', 'concierge', 'Services'],
        ];

        $amenities = [];
        foreach ($amenitiesData as $data) {
            $amenity = new Amenity();
            $amenity->setName($data[0]);
            $amenity->setIcon($data[1]);
            $amenity->setCategory($data[2]);
            $manager->persist($amenity);
            $amenities[] = $amenity;
        }

        return $amenities;
    }

    private function createProperties(ObjectManager $manager, array $users, array $amenities): array
    {
        $properties = [];
        $propertyTypes = ['Appartement', 'Maison', 'Villa', 'Copropriété', 'Studio', 'Loft', 'Cottage', 'Chalet'];
        $cities = ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier', 'Bordeaux', 'Lille', 'Cannes', 'Biarritz', 'Annecy', 'Avignon', 'Aix-en-Provence'];
        $addresses = ['Rue de la République', 'Avenue des Champs-Élysées', 'Boulevard Saint-Michel', 'Place de la Bastille', 'Rue du Faubourg Saint-Antoine', 'Avenue Montaigne', 'Rue de Rivoli', 'Boulevard Haussmann'];

        $titles = [
            'Appartement cosy au cœur de la ville',
            'Maison moderne avec jardin',
            'Villa de luxe avec piscine',
            'Studio design en centre-ville',
            'Loft spacieux avec vue panoramique',
            'Cottage charmant à la campagne',
            'Appartement lumineux avec balcon',
            'Maison traditionnelle rénovée',
        ];

        $descriptions = [
            'Magnifique propriété située dans un quartier calme et résidentiel. Parfait pour un séjour en famille ou entre amis.',
            'Appartement moderne et confortable, idéalement situé près des transports en commun et des commerces.',
            'Villa exceptionnelle avec piscine privée, jardin et terrasse. Vue imprenable sur la mer.',
            'Studio récemment rénové, équipé de tout le nécessaire pour un séjour agréable.',
            'Loft de caractère dans un ancien entrepôt rénové, avec hauts plafonds et grandes fenêtres.',
            'Cottage authentique avec cheminée, situé dans un petit village pittoresque.',
            'Appartement spacieux et lumineux avec balcon offrant une belle vue sur la ville.',
            'Maison de caractère avec jardin, parfaite pour se ressourcer en famille.',
        ];

        for ($i = 0; $i < 30; $i++) {
            $property = new Property();
            $property->setHost($users[rand(0, count($users) - 1)]);
            $property->setTitle($titles[array_rand($titles)]);
            $property->setDescription($descriptions[array_rand($descriptions)]);
            $property->setPropertyType($propertyTypes[array_rand($propertyTypes)]);
            $city = $cities[array_rand($cities)];
            $property->setAddress(rand(1, 200) . ' ' . $addresses[array_rand($addresses)]);
            $property->setCity($city);
            $property->setCountry('France');
            $property->setPostalCode(str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT));
            $property->setLatitude((string)(43.6 + (rand(-100, 100) / 100)));
            $property->setLongitude((string)(1.4 + (rand(-100, 100) / 100)));
            $property->setMaxGuests(rand(2, 10));
            $property->setBedrooms(rand(1, 5));
            $property->setBeds(rand(1, 6));
            $property->setBathrooms((string)(rand(1, 4) + rand(0, 1) * 0.5));
            $property->setPricePerNight((string)(rand(50, 500) + rand(0, 99) / 100));
            $property->setCleaningFee((string)(rand(20, 100) + rand(0, 99) / 100));
            $property->setStatus(['actif', 'inactif', 'en_attente'][rand(0, 2)]);
            $property->setCreatedAt(new \DateTimeImmutable('-' . rand(60, 730) . ' days'));
            $property->setUpdatedAt(new \DateTimeImmutable('-' . rand(1, 60) . ' days'));
            $manager->persist($property);

            if (count($amenities) > 0) {
                $numAmenities = rand(5, min(10, count($amenities)));
                $selectedAmenities = array_rand($amenities, $numAmenities);
                if (!is_array($selectedAmenities)) {
                    $selectedAmenities = [$selectedAmenities];
                }
                foreach ($selectedAmenities as $amenityIndex) {
                    $property->addAmenity($amenities[$amenityIndex]);
                }
            }

            for ($j = 0; $j < rand(3, 8); $j++) {
                $photo = new PropertyPhoto();
                $photo->setProperty($property);
                $photo->setPhotoUrl('https://picsum.photos/800/600?random=' . ($i * 10 + $j));
                $photo->setDisplayOrder($j);
                $photo->setIsCover($j === 0);
                $photo->setUploadedAt(new \DateTimeImmutable('-' . rand(1, 60) . ' days'));
                $manager->persist($photo);
            }

            $startDate = new \DateTimeImmutable('today');
            for ($j = 0; $j < 90; $j++) {
                $date = $startDate->modify("+$j days");
                $availability = new Availability();
                $availability->setProperty($property);
                $availability->setDate($date);
                $availability->setIsAvailable(rand(0, 10) > 1);
                if (rand(0, 5) === 0) {
                    $basePrice = (float)$property->getPricePerNight();
                    $availability->setPriceOverride((string)($basePrice * (1 + (rand(-20, 30) / 100))));
                }
                $manager->persist($availability);
            }

            $manager->persist($property);
            $properties[] = $property;
        }

        return $properties;
    }

    private function createBookings(ObjectManager $manager, array $properties, array $users): array
    {
        $bookings = [];
        $statuses = ['en_attente', 'confirmé', 'terminé', 'annulé'];

        for ($i = 0; $i < 50; $i++) {
            $property = $properties[array_rand($properties)];
            $guest = $users[array_rand($users)];
            while ($guest === $property->getHost()) {
                $guest = $users[array_rand($users)];
            }

            $checkIn = new \DateTimeImmutable('+' . rand(1, 60) . ' days');
            $nights = rand(1, 14);
            $checkOut = $checkIn->modify("+$nights days");

            $booking = new Booking();
            $booking->setProperty($property);
            $booking->setGuest($guest);
            $booking->setCheckInDate($checkIn);
            $booking->setCheckOutDate($checkOut);
            $booking->setNumberOfGuests(rand(1, min($property->getMaxGuests(), 6)));
            $basePrice = (float)$property->getPricePerNight();
            $totalPrice = $basePrice * $nights;
            if ($property->getCleaningFee()) {
                $totalPrice += (float)$property->getCleaningFee();
            }
            $booking->setTotalPrice((string)$totalPrice);
            $booking->setCommissionAmount((string)($totalPrice * 0.12));
            $booking->setStatus($statuses[array_rand($statuses)]);
            $booking->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
            $booking->setUpdatedAt(new \DateTimeImmutable('-' . rand(0, 7) . ' days'));

            $manager->persist($booking);
            $bookings[] = $booking;
        }

        return $bookings;
    }

    private function createPayments(ObjectManager $manager, array $bookings): void
    {
        $paymentMethods = ['carte_bancaire', 'paypal', 'virement_bancaire', 'stripe'];
        $statuses = ['en_attente', 'terminé', 'échoué', 'remboursé'];

        foreach ($bookings as $booking) {
            if ($booking->getStatus() === 'annulé') {
                continue;
            }

            $payment = new Payment();
            $payment->setBooking($booking);
            $payment->setAmount($booking->getTotalPrice());
            $payment->setCurrency('EUR');
            $payment->setPaymentMethod($paymentMethods[array_rand($paymentMethods)]);
            $payment->setStatus($statuses[array_rand($statuses)]);
            $payment->setTransactionId('TXN' . strtoupper(uniqid()));
            if ($payment->getStatus() === 'terminé') {
                $payment->setPaidAt(new \DateTimeImmutable('-' . rand(1, 10) . ' days'));
                if (rand(0, 3) === 0) {
                    $payment->setReleasedAt(new \DateTimeImmutable('-' . rand(1, 5) . ' days'));
                }
            }
            $payment->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 15) . ' days'));

            $manager->persist($payment);
        }
    }

    private function createReviews(ObjectManager $manager, array $bookings, array $users): void
    {
        foreach ($bookings as $booking) {
            if ($booking->getStatus() !== 'terminé') {
                continue;
            }

            if (rand(0, 3) > 0) {
                $review = new Review();
                $review->setBooking($booking);
                $review->setReviewer($booking->getGuest());
                $review->setReviewee($booking->getProperty()->getHost());
                $review->setRating(rand(3, 5));
                $review->setComment('Excellent séjour ! ' . ['Très bon accueil', 'Propriété conforme aux photos', 'Emplacement parfait', 'Je recommande vivement', 'Séjour parfait'][rand(0, 4)]);
                $review->setIsFromGuest(true);
                $review->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
                $review->setUpdatedAt(new \DateTimeImmutable('-' . rand(0, 7) . ' days'));
                $manager->persist($review);
            }

            if (rand(0, 3) > 0) {
                $review = new Review();
                $review->setBooking($booking);
                $review->setReviewer($booking->getProperty()->getHost());
                $review->setReviewee($booking->getGuest());
                $review->setRating(rand(4, 5));
                $review->setComment('Invité parfait ! ' . ['Très respectueux', 'Communication excellente', 'Je recommande', 'Hôte idéal'][rand(0, 3)]);
                $review->setIsFromGuest(false);
                $review->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
                $review->setUpdatedAt(new \DateTimeImmutable('-' . rand(0, 7) . ' days'));
                $manager->persist($review);
            }
        }
    }

    private function createMessages(ObjectManager $manager, array $bookings, array $users): void
    {
        $messages = [
            'Bonjour, j\'aimerais réserver pour ces dates. Est-ce possible ?',
            'Merci pour votre réponse rapide !',
            'Parfait, je confirme ma réservation.',
            'À quelle heure puis-je arriver ?',
            'Merci pour votre accueil, tout était parfait !',
            'Pouvez-vous me donner l\'adresse exacte ?',
            'Y a-t-il un parking disponible ?',
            'Merci beaucoup pour ce séjour agréable.',
        ];

        foreach ($bookings as $booking) {
            $messageCount = rand(2, 8);
            for ($i = 0; $i < $messageCount; $i++) {
                $isFromGuest = ($i % 2 === 0);
                $message = new Message();
                $message->setSender($isFromGuest ? $booking->getGuest() : $booking->getProperty()->getHost());
                $message->setRecipient($isFromGuest ? $booking->getProperty()->getHost() : $booking->getGuest());
                $message->setBooking($booking);
                $message->setContent($messages[array_rand($messages)]);
                $message->setIsRead($i < $messageCount - 1);
                $message->setSentAt(new \DateTimeImmutable('-' . ($messageCount - $i) . ' days'));

                $manager->persist($message);
            }
        }
    }

    private function createBadges(ObjectManager $manager): array
    {
        $badgesData = [
            ['Premier voyage', 'Complétez votre première réservation', 'first-trip', 'accomplissement', 'premiere_reservation', 1],
            ['Explorateur', 'Réservez 5 propriétés', 'explorer', 'accomplissement', 'reservations', 5],
            ['Voyageur expérimenté', 'Réservez 10 propriétés', 'traveler', 'accomplissement', 'reservations', 10],
            ['Hôte confirmé', 'Listez votre première propriété', 'host', 'hébergement', 'proprietes', 1],
            ['Super hôte', 'Listez 3 propriétés', 'super-host', 'hébergement', 'proprietes', 3],
            ['Évaluateur', 'Laissez 5 avis', 'reviewer', 'social', 'avis', 5],
            ['Ambassadeur', 'Visitez 3 pays différents', 'ambassador', 'voyage', 'pays', 3],
        ];

        $badges = [];
        foreach ($badgesData as $data) {
            $badge = new Badge();
            $badge->setName($data[0]);
            $badge->setDescription($data[1]);
            $badge->setIconUrl('https://example.com/icons/' . $data[2] . '.png');
            $badge->setCategory($data[3]);
            $badge->setRequirementType($data[4]);
            $badge->setRequirementValue($data[5]);
            $manager->persist($badge);
            $badges[] = $badge;
        }

        return $badges;
    }

    private function createUserBadges(ObjectManager $manager, array $users, array $badges): void
    {
        foreach ($users as $user) {
            $numBadges = rand(0, min(3, count($badges)));
            if ($numBadges === 0 || count($badges) === 0) {
                continue;
            }
            $earnedBadges = array_rand($badges, $numBadges);
            if (!is_array($earnedBadges)) {
                $earnedBadges = [$earnedBadges];
            }
            foreach ($earnedBadges as $badgeIndex) {
                $userBadge = new UserBadge();
                $userBadge->setUser($user);
                $userBadge->setBadge($badges[$badgeIndex]);
                $userBadge->setEarnedAt(new \DateTimeImmutable('-' . rand(1, 180) . ' days'));
                $manager->persist($userBadge);
            }
        }
    }

    private function createChallenges(ObjectManager $manager): array
    {
        $challengesData = [
            ['Défi du mois', 'Réservez 2 propriétés ce mois-ci', 'reservation_mensuelle', 2, 100, true],
            ['Explorateur de l\'été', 'Réservez une propriété en été', 'saisonnier', 1, 150, true],
            ['Hôte actif', 'Listez une nouvelle propriété', 'hébergement', 1, 200, true],
            ['Communautaire', 'Laissez 3 avis ce mois-ci', 'avis', 3, 75, true],
        ];

        $challenges = [];
        foreach ($challengesData as $data) {
            $challenge = new Challenge();
            $challenge->setName($data[0]);
            $challenge->setDescription($data[1]);
            $challenge->setChallengeType($data[2]);
            $challenge->setTargetValue($data[3]);
            $challenge->setPointsReward($data[4]);
            $startDate = new \DateTimeImmutable('first day of this month');
            $challenge->setStartDate($startDate);
            $challenge->setEndDate($startDate->modify('+1 month -1 day'));
            $challenge->setIsActive($data[5]);
            $challenge->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
            $manager->persist($challenge);
            $challenges[] = $challenge;
        }

        return $challenges;
    }

    private function createUserChallenges(ObjectManager $manager, array $users, array $challenges): void
    {
        foreach ($users as $user) {
            foreach ($challenges as $challenge) {
                if (rand(0, 2) > 0) {
                    $userChallenge = new UserChallenge();
                    $userChallenge->setUser($user);
                    $userChallenge->setChallenge($challenge);
                    $userChallenge->setCurrentProgress(rand(0, $challenge->getTargetValue()));
                    $userChallenge->setIsCompleted($userChallenge->getCurrentProgress() >= $challenge->getTargetValue());
                    if ($userChallenge->isCompleted()) {
                        $userChallenge->setCompletedAt(new \DateTimeImmutable('-' . rand(1, 15) . ' days'));
                    }
                    $userChallenge->setStartedAt(new \DateTimeImmutable('-' . rand(1, 20) . ' days'));
                    $manager->persist($userChallenge);
                }
            }
        }
    }

    private function createRewards(ObjectManager $manager): array
    {
        $rewardsData = [
            ['Réduction 10%', 'Obtenez 10% de réduction sur votre prochaine réservation', 'reduction_pourcentage', 10.0, null, 500],
            ['Réduction 20%', 'Obtenez 20% de réduction sur votre prochaine réservation', 'reduction_pourcentage', 20.0, null, 1000],
            ['Crédit 50€', 'Obtenez un crédit de 50€ pour votre prochaine réservation', 'reduction_montant', null, 50.0, 750],
            ['Nuit gratuite', 'Obtenez une nuit gratuite', 'nuit_gratuite', null, null, 2000],
        ];

        $rewards = [];
        foreach ($rewardsData as $data) {
            $reward = new Reward();
            $reward->setName($data[0]);
            $reward->setDescription($data[1]);
            $reward->setRewardType($data[2]);
            $reward->setDiscountPercentage($data[3]);
            $reward->setDiscountAmount($data[4]);
            $reward->setPointsRequired($data[5]);
            $reward->setIsActive(true);
            $reward->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 60) . ' days'));
            $manager->persist($reward);
            $rewards[] = $reward;
        }

        return $rewards;
    }

    private function createUserRewards(ObjectManager $manager, array $users, array $rewards): void
    {
        foreach ($users as $user) {
            if (rand(0, 3) > 0) {
                $reward = $rewards[array_rand($rewards)];
                $userReward = new UserReward();
                $userReward->setUser($user);
                $userReward->setReward($reward);
                $userReward->setStatus(['gagné', 'utilisé', 'expiré'][rand(0, 2)]);
                $earnedAt = new \DateTimeImmutable('-' . rand(1, 90) . ' days');
                $userReward->setEarnedAt($earnedAt);
                if ($userReward->getStatus() === 'utilisé') {
                    $userReward->setRedeemedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
                }
                $userReward->setExpiresAt($earnedAt->modify('+90 days'));
                $manager->persist($userReward);
            }
        }
    }

    private function createGamificationStats(ObjectManager $manager, array $users): void
    {
        foreach ($users as $user) {
            $stats = new GamificationUserStats();
            $stats->setUser($user);
            $bookingsCount = rand(0, 15);
            $reviewsCount = rand(0, 10);
            $countriesVisited = rand(1, 5);
            $stats->setBookingsCount($bookingsCount);
            $stats->setReviewsCount($reviewsCount);
            $stats->setCountriesVisited($countriesVisited);
            $totalPoints = ($bookingsCount * 100) + ($reviewsCount * 50) + ($countriesVisited * 200);
            $stats->setTotalPoints($totalPoints);
            $stats->setLevel((int)($totalPoints / 500) + 1);
            $stats->setCreatedAt(new \DateTimeImmutable('-' . rand(30, 365) . ' days'));
            $stats->setUpdatedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
            $manager->persist($stats);
        }
    }
}
