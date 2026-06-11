<?php

namespace App\DataFixtures;

use App\Entity\AdminAction;
use App\Entity\Amenity;
use App\Entity\AuthProvider;
use App\Entity\AvailabilityBlock;
use App\Entity\Booking;
use App\Entity\BookingHistory;
use App\Enum\BookingStatus;
use App\Entity\Conversation;
use App\Entity\EmailVerification;
use App\Entity\Listing;
use App\Entity\ListingAvailability;
use App\Entity\ListingLocation;
use App\Entity\ListingPhoto;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\PasswordReset;
use App\Entity\Payment;
use App\Entity\Payout;
use App\Entity\RefreshToken;
use App\Entity\Report;
use App\Entity\Review;
use App\Entity\ReviewPhoto;
use App\Entity\User;
use App\Entity\UserIdentity;
use App\Entity\Wishlist;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        $amenityData = [
            ['WiFi', 'wifi'],
            ['Piscine', 'pool'],
            ['Parking', 'parking'],
            ['Cuisine équipée', 'kitchen'],
            ['Climatisation', 'ac'],
            ['Lave-linge', 'washing-machine'],
            ['Télévision', 'tv'],
            ['Balcon', 'balcony'],
            ['Animaux acceptés', 'pets'],
            ['Barbecue', 'bbq'],
        ];
        $amenities = [];
        foreach ($amenityData as [$name, $icon]) {
            $a = (new Amenity())->setName($name)->setIcon($icon);
            $manager->persist($a);
            $amenities[] = $a;
        }

        $usersData = [
            ['Alice', 'Martin',   'alice@example.com',   '+33612345678', 'host'],
            ['Bob',   'Dupont',   'bob@example.com',     '+33698765432', 'host'],
            ['Carol', 'Bernard',  'carol@example.com',   '+33611223344', 'guest'],
            ['David', 'Lambert',  'david@example.com',   '+33655443322', 'guest'],
            ['Eva',   'Moreau',   'eva@example.com',     null,           'guest'],
            ['Frank', 'Leroy',    'frank@example.com',   null,           'admin'],
        ];
        $users = [];
        foreach ($usersData as [$first, $last, $email, $phone, $role]) {
            $u = (new User())
                ->setFirstName($first)
                ->setLastName($last)
                ->setEmail($email)
                ->setPhone($phone)
                ->setPasswordHash(password_hash('password123', PASSWORD_BCRYPT))
                ->setRole($role)
                ->setPreferredLanguage('fr')
                ->setPreferredCurrency('EUR')
                ->setEmailVerified(true)
                ->setPhoneVerified($phone !== null)
                ->setIdentityVerified(false)
                ->setStatus('active')
                ->setCreatedAt($now)
                ->setUpdatedAt($now);
            $manager->persist($u);
            $users[] = $u;
        }
        [$alice, $bob, $carol, $david, $eva, $frank] = $users;

        $identity = (new UserIdentity())
            ->setUser($alice)
            ->setDocumentType('passport')
            ->setVerificationStatus('verified')
            ->setVerifiedAt($now)
            ->setCreatedAt($now);
        $manager->persist($identity);

        $authProvider = (new AuthProvider())
            ->setUser($bob)
            ->setProvider('google')
            ->setProviderUserId('google-uid-bob-123')
            ->setCreatedAt($now);
        $manager->persist($authProvider);

        foreach ([$alice, $bob, $carol] as $u) {
            $rt = (new RefreshToken())
                ->setUser($u)
                ->setTokenHash(hash('sha256', uniqid('rt_', true)))
                ->setExpiresAt($now->modify('+30 days'))
                ->setRevoked(false)
                ->setCreatedAt($now);
            $manager->persist($rt);
        }

        $ev = (new EmailVerification())
            ->setUser($eva)
            ->setToken(bin2hex(random_bytes(32)))
            ->setExpiresAt($now->modify('+1 day'))
            ->setUsed(false)
            ->setCreatedAt($now);
        $manager->persist($ev);

        $pr = (new PasswordReset())
            ->setUser($carol)
            ->setToken(bin2hex(random_bytes(32)))
            ->setExpiresAt($now->modify('+2 hours'))
            ->setUsed(false)
            ->setCreatedAt($now);
        $manager->persist($pr);

        $listingsData = [
            [
                'host'     => $alice,
                'title'    => 'Appartement cosy à Paris 11e',
                'desc'     => 'Bel appartement de 40m² au cœur du 11e arrondissement, idéal pour découvrir Paris.',
                'type'     => 'apartment', 'room' => 'entire_home',
                'guests'   => 2, 'beds' => 1, 'bedrooms' => 1, 'baths' => 1,
                'price'    => '95.00', 'cleaning' => '20.00', 'service' => '15.00',
                'currency' => 'EUR', 'status' => 'published',
                'instant'  => true, 'cancel' => 'flexible',
                'country'  => 'France', 'city' => 'Paris', 'state' => 'Île-de-France',
                'address'  => '42 rue de la Roquette', 'postal' => '75011',
                'lat' => '48.8538800', 'lon' => '2.3729700',
                'amenities' => [0, 3, 4, 6],
                'photos' => [
                    ['https://images.unsplash.com/photo-1522708323590-d24dbb6b0267', true],
                    ['https://images.unsplash.com/photo-1560448204-e02f11c3d0e2', false],
                ],
            ],
            [
                'host'     => $alice,
                'title'    => 'Studio vue mer à Nice',
                'desc'     => 'Studio lumineux avec vue imprenable sur la Méditerranée, à 2 min de la plage.',
                'type'     => 'apartment', 'room' => 'entire_home',
                'guests'   => 2, 'beds' => 1, 'bedrooms' => 1, 'baths' => 1,
                'price'    => '130.00', 'cleaning' => '30.00', 'service' => '18.00',
                'currency' => 'EUR', 'status' => 'published',
                'instant'  => false, 'cancel' => 'moderate',
                'country'  => 'France', 'city' => 'Nice', 'state' => "Provence-Alpes-Côte d'Azur",
                'address'  => '8 promenade des Anglais', 'postal' => '06000',
                'lat' => '43.6956400', 'lon' => '7.2656000',
                'amenities' => [0, 1, 4, 6],
                'photos' => [
                    ['https://images.unsplash.com/photo-1598928506311-c55ded91a20c', true],
                ],
            ],
            [
                'host'     => $bob,
                'title'    => 'Chalet montagne à Chamonix',
                'desc'     => 'Chalet traditionnel avec cheminée, ski au pied des pistes du Mont-Blanc.',
                'type'     => 'house', 'room' => 'entire_home',
                'guests'   => 6, 'beds' => 3, 'bedrooms' => 3, 'baths' => 2,
                'price'    => '280.00', 'cleaning' => '60.00', 'service' => '35.00',
                'currency' => 'EUR', 'status' => 'published',
                'instant'  => true, 'cancel' => 'strict',
                'country'  => 'France', 'city' => 'Chamonix', 'state' => 'Auvergne-Rhône-Alpes',
                'address'  => '15 chemin des Grands-Prés', 'postal' => '74400',
                'lat' => '45.9237000', 'lon' => '6.8694400',
                'amenities' => [0, 2, 3, 9],
                'photos' => [
                    ['https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8', true],
                    ['https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1', false],
                ],
            ],
            [
                'host'     => $bob,
                'title'    => 'Maison avec jardin à Bordeaux',
                'desc'     => 'Grande maison bordelaise avec terrasse, jardin privatif et parking.',
                'type'     => 'house', 'room' => 'entire_home',
                'guests'   => 4, 'beds' => 2, 'bedrooms' => 2, 'baths' => 1,
                'price'    => '150.00', 'cleaning' => '40.00', 'service' => '22.00',
                'currency' => 'EUR', 'status' => 'published',
                'instant'  => true, 'cancel' => 'flexible',
                'country'  => 'France', 'city' => 'Bordeaux', 'state' => 'Nouvelle-Aquitaine',
                'address'  => '27 rue du Mirail', 'postal' => '33000',
                'lat' => '44.8378000', 'lon' => '-0.5792000',
                'amenities' => [0, 2, 3, 5, 8, 9],
                'photos' => [
                    ['https://images.unsplash.com/photo-1568605114967-8130f3a36994', true],
                ],
            ],
        ];

        $listings = [];
        foreach ($listingsData as $data) {
            $listing = (new Listing())
                ->setHost($data['host'])
                ->setTitle($data['title'])
                ->setDescription($data['desc'])
                ->setPropertyType($data['type'])
                ->setRoomType($data['room'])
                ->setMaxGuests($data['guests'])
                ->setBeds($data['beds'])
                ->setBedrooms($data['bedrooms'])
                ->setBathrooms($data['baths'])
                ->setPricePerNight($data['price'])
                ->setCleaningFee($data['cleaning'])
                ->setServiceFee($data['service'])
                ->setCurrency($data['currency'])
                ->setStatus($data['status'])
                ->setInstantBooking($data['instant'])
                ->setCancellationPolicy($data['cancel'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);
            foreach ($data['amenities'] as $idx) {
                $listing->addAmenity($amenities[$idx]);
            }
            $manager->persist($listing);

            $location = (new ListingLocation())
                ->setListing($listing)
                ->setCountry($data['country'])
                ->setCity($data['city'])
                ->setState($data['state'])
                ->setAddressLine1($data['address'])
                ->setPostalCode($data['postal'])
                ->setLatitude($data['lat'])
                ->setLongitude($data['lon']);
            $manager->persist($location);

            foreach ($data['photos'] as $pos => [$url, $isCover]) {
                $photo = (new ListingPhoto())
                    ->setListing($listing)
                    ->setImageUrl($url)
                    ->setPosition($pos)
                    ->setIsCover($isCover)
                    ->setCreatedAt($now);
                $manager->persist($photo);
            }

            for ($d = 1; $d <= 30; $d++) {
                $avail = (new ListingAvailability())
                    ->setListing($listing)
                    ->setAvailableDate($now->modify("+{$d} days"))
                    ->setIsAvailable(true);
                $manager->persist($avail);
            }

            $listings[] = $listing;
        }
        [$paris, $nice, $chamonix, $bordeaux] = $listings;

        $bookingsData = [
            [
                'listing'  => $paris,
                'guest'    => $carol,
                'checkIn'  => $now->modify('-20 days'),
                'checkOut' => $now->modify('-17 days'),
                'nights'   => 3,
                'guests'   => 2,
                'base'     => '285.00', 'cleaning' => '20.00', 'service' => '45.00',
                'taxes'    => '35.00', 'total' => '385.00',
                'status'   => 'completed',
            ],
            [
                'listing'  => $nice,
                'guest'    => $david,
                'checkIn'  => $now->modify('-10 days'),
                'checkOut' => $now->modify('-7 days'),
                'nights'   => 3,
                'guests'   => 2,
                'base'     => '390.00', 'cleaning' => '30.00', 'service' => '54.00',
                'taxes'    => '47.00', 'total' => '521.00',
                'status'   => 'completed',
            ],
            [
                'listing'  => $chamonix,
                'guest'    => $carol,
                'checkIn'  => $now->modify('+15 days'),
                'checkOut' => $now->modify('+22 days'),
                'nights'   => 7,
                'guests'   => 4,
                'base'     => '1960.00', 'cleaning' => '60.00', 'service' => '210.00',
                'taxes'    => '177.00', 'total' => '2407.00',
                'status'   => 'confirmed',
            ],
            [
                'listing'  => $bordeaux,
                'guest'    => $eva,
                'checkIn'  => $now->modify('+5 days'),
                'checkOut' => $now->modify('+8 days'),
                'nights'   => 3,
                'guests'   => 2,
                'base'     => '450.00', 'cleaning' => '40.00', 'service' => '65.00',
                'taxes'    => '51.00', 'total' => '606.00',
                'status'   => 'pending',
            ],
        ];

        $bookings = [];
        foreach ($bookingsData as $data) {
            $status = BookingStatus::from($data['status']);

            $b = (new Booking())
                ->setListing($data['listing'])
                ->setGuest($data['guest'])
                ->setCheckIn($data['checkIn'])
                ->setCheckOut($data['checkOut'])
                ->setNightsCount($data['nights'])
                ->setGuestsCount($data['guests'])
                ->setBaseAmount($data['base'])
                ->setCleaningFee($data['cleaning'])
                ->setServiceFee($data['service'])
                ->setTaxesAmount($data['taxes'])
                ->setTotalAmount($data['total'])
                ->setCurrency('EUR')
                ->setBookingStatus($status)
                ->setCreatedAt($now);
            if (in_array($data['status'], ['confirmed', 'completed'], true)) {
                $b->setConfirmedAt($now->modify('-1 day'));
            }
            $manager->persist($b);

            $createdHistory = (new BookingHistory())
                ->setStatus(BookingStatus::Pending)
                ->setAuthor($data['guest'])
                ->setComment('Demande de réservation envoyée.')
                ->setCreatedAt($data['checkIn']->modify('-30 days'));
            $b->addHistory($createdHistory);
            $manager->persist($createdHistory);

            if ($status !== BookingStatus::Pending) {
                $confirmedHistory = (new BookingHistory())
                    ->setStatus(BookingStatus::Confirmed)
                    ->setAuthor($data['listing']->getHost())
                    ->setComment('Demande acceptée par l\'hôte.')
                    ->setCreatedAt($data['checkIn']->modify('-29 days'));
                $b->addHistory($confirmedHistory);
                $manager->persist($confirmedHistory);
            }

            $bookings[] = $b;
        }
        [$bookingParis, $bookingNice, $bookingChamonix, $bookingBordeaux] = $bookings;

        $blocksData = [
            [$nice, $now->modify('+3 days'), $now->modify('+6 days'), 'Travaux de peinture'],
            [$chamonix, $now->modify('+40 days'), $now->modify('+45 days'), 'Usage personnel'],
        ];
        foreach ($blocksData as [$listing, $start, $end, $reason]) {
            $block = (new AvailabilityBlock())
                ->setListing($listing)
                ->setStartDate($start)
                ->setEndDate($end)
                ->setReason($reason)
                ->setSource(AvailabilityBlock::SOURCE_MANUAL);
            $manager->persist($block);
        }

        $paymentsData = [
            [$bookingParis,  $carol, '385.00', '19.25', '346.50', 'succeeded', $now->modify('-20 days')],
            [$bookingNice,   $david, '521.00', '26.05', '468.90', 'succeeded', $now->modify('-10 days')],
        ];
        foreach ($paymentsData as [$booking, $payer, $amount, $fee, $payout, $status, $paidAt]) {
            $p = (new Payment())
                ->setBooking($booking)
                ->setPayer($payer)
                ->setAmount($amount)
                ->setCurrency('EUR')
                ->setPlatformFee($fee)
                ->setHostPayout($payout)
                ->setPaymentMethod('card')
                ->setStripePaymentIntentId('pi_' . bin2hex(random_bytes(12)))
                ->setPaymentStatus($status)
                ->setPaidAt($paidAt)
                ->setCreatedAt($paidAt);
            $manager->persist($p);
        }

        $payout1 = (new Payout())
            ->setHost($alice)
            ->setBooking($bookingParis)
            ->setAmount('346.50')
            ->setCurrency('EUR')
            ->setPayoutStatus('paid')
            ->setPaidAt($now->modify('-15 days'));
        $manager->persist($payout1);

        $payout2 = (new Payout())
            ->setHost($alice)
            ->setBooking($bookingNice)
            ->setAmount('468.90')
            ->setCurrency('EUR')
            ->setPayoutStatus('paid')
            ->setPaidAt($now->modify('-5 days'));
        $manager->persist($payout2);

        $review1 = (new Review())
            ->setBooking($bookingParis)
            ->setListing($paris)
            ->setReviewer($carol)
            ->setRatingOverall(5)
            ->setRatingCleanliness(5)
            ->setRatingCommunication(5)
            ->setRatingLocation(4)
            ->setRatingAccuracy(5)
            ->setComment('Séjour parfait ! Appartement exactement comme sur les photos, hôte très réactif. Je recommande vivement.')
            ->setCreatedAt($now->modify('-16 days'));
        $manager->persist($review1);

        $photoReview = (new ReviewPhoto())
            ->setReview($review1)
            ->setImageUrl('https://images.unsplash.com/photo-1522708323590-d24dbb6b0267');
        $manager->persist($photoReview);

        $review2 = (new Review())
            ->setBooking($bookingNice)
            ->setListing($nice)
            ->setReviewer($david)
            ->setRatingOverall(4)
            ->setRatingCleanliness(4)
            ->setRatingCommunication(5)
            ->setRatingLocation(5)
            ->setRatingAccuracy(4)
            ->setComment('Vue mer magnifique, emplacement idéal. Studio un peu petit mais bien équipé.')
            ->setCreatedAt($now->modify('-6 days'));
        $manager->persist($review2);

        $convsData = [
            [$bookingParis,  $alice, $carol, 'Bonjour Alice ! Je serai à Paris avec mon conjoint. Y a-t-il un parking à proximité ?', 'Bonjour Carol ! Pas de parking dans l\'immeuble, mais un parking payant est disponible à 200m.'],
            [$bookingChamonix, $bob, $carol, 'Bonjour Bob, pouvez-vous nous indiquer où récupérer les clés à notre arrivée ?', 'Bonjour ! Les clés sont dans une boîte à code à l\'entrée. Je vous enverrai le code 48h avant.'],
        ];
        foreach ($convsData as [$booking, $host, $guest, $guestMsg, $hostMsg]) {
            $conv = (new Conversation())
                ->setBooking($booking)
                ->setCreatedAt($now->modify('-2 days'));
            $conv->addParticipant($host);
            $conv->addParticipant($guest);
            $manager->persist($conv);

            $m1 = (new Message())
                ->setConversation($conv)
                ->setSender($guest)
                ->setMessage($guestMsg)
                ->setIsRead(true)
                ->setCreatedAt($now->modify('-2 days'));
            $manager->persist($m1);

            $m2 = (new Message())
                ->setConversation($conv)
                ->setSender($host)
                ->setMessage($hostMsg)
                ->setIsRead(false)
                ->setCreatedAt($now->modify('-1 day'));
            $manager->persist($m2);
        }

        $directConv = (new Conversation())->setCreatedAt($now->modify('-3 days'));
        $directConv->addParticipant($alice);
        $directConv->addParticipant($david);
        $manager->persist($directConv);

        $dm = (new Message())
            ->setConversation($directConv)
            ->setSender($david)
            ->setMessage('Bonjour Alice, votre appartement de Nice est-il disponible début septembre ?')
            ->setIsRead(false)
            ->setCreatedAt($now->modify('-3 days'));
        $manager->persist($dm);

        $notifData = [
            [$carol, 'booking_confirmed', 'push', 'Réservation confirmée', 'Votre réservation à Paris est confirmée pour les 3 nuits du ' . $now->modify('-20 days')->format('d/m') . '.'],
            [$david, 'booking_confirmed', 'email', 'Réservation confirmée', 'Votre réservation à Nice a bien été prise en compte.'],
            [$alice, 'new_review',        'push',  'Nouvel avis reçu',     'Carol a laissé un avis 5 étoiles sur votre appartement Paris 11e.'],
            [$alice, 'payout_sent',       'email', 'Virement effectué',    'Un virement de 346,50€ a été envoyé sur votre compte bancaire.'],
            [$carol, 'message_received',  'push',  'Nouveau message',      'Alice vous a répondu concernant votre réservation à Paris.'],
        ];
        foreach ($notifData as [$user, $type, $channel, $title, $content]) {
            $notif = (new Notification())
                ->setUser($user)
                ->setType($type)
                ->setChannel($channel)
                ->setTitle($title)
                ->setContent($content)
                ->setIsRead(false)
                ->setCreatedAt($now);
            $manager->persist($notif);
        }

        $wishlist1 = (new Wishlist())
            ->setUser($carol)
            ->setName('Mes coups de cœur')
            ->setCreatedAt($now);
        $wishlist1->addListing($nice);
        $wishlist1->addListing($chamonix);
        $manager->persist($wishlist1);

        $wishlist2 = (new Wishlist())
            ->setUser($david)
            ->setName('Vacances été 2026')
            ->setCreatedAt($now);
        $wishlist2->addListing($paris);
        $wishlist2->addListing($bordeaux);
        $manager->persist($wishlist2);

        $report = (new Report())
            ->setReporter($eva)
            ->setListing($bordeaux)
            ->setReason('Les photos ne correspondent pas à la réalité : la vue annoncée est inexistante.')
            ->setReportStatus('pending')
            ->setCreatedAt($now);
        $manager->persist($report);

        $adminAction = (new AdminAction())
            ->setAdmin($frank)
            ->setActionType('listing_suspend')
            ->setTargetType('listing')
            ->setDescription('Listing suspendu temporairement suite à signalement utilisateur en attente de vérification.')
            ->setCreatedAt($now);
        $manager->persist($adminAction);

        $manager->flush();
    }
}
