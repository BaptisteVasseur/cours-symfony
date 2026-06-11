<?php

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\PropertyImage;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Enum\BookingStatus;
use App\Enum\PropertyStatus;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        $users = $this->createUsers($manager);
        $properties = $this->createProperties($manager, $users);
        $bookings = $this->createBookings($manager, $users, $properties);
        $this->createReviews($manager, $bookings);
        $this->createConversations($manager, $bookings);
        $this->createAvailabilities($manager, $properties);

        $manager->flush();
    }

    private function createUsers(ObjectManager $manager): array
    {
        $data = [
            ['admin@airbnb.com', 'Admin', 'Dupont', UserRole::ADMIN, '+33600000001'],
            ['alice@host.com', 'Alice', 'Martin', UserRole::HOST, '+33600000002'],
            ['bob@host.com', 'Bob', 'Bernard', UserRole::HOST, '+33600000003'],
            ['carol@traveler.com', 'Carol', 'Petit', UserRole::TRAVELER, '+33600000004'],
            ['david@traveler.com', 'David', 'Moreau', UserRole::TRAVELER, '+33600000005'],
        ];

        $users = [];
        foreach ($data as [$email, $first, $last, $role, $phone]) {
            $user = new User();
            $user->setFirstName($first)
                ->setLastName($last)
                ->setEmail($email)
                ->setPhone($phone)
                ->setRole($role)
                ->setPasswordHash($this->hasher->hashPassword($user, 'password123'));

            $pref = new UserPreference();
            $pref->setUser($user)->setLanguage('fr')->setCurrency('EUR');
            $user->setPreference($pref);

            $manager->persist($user);
            $manager->persist($pref);
            $users[$email] = $user;
        }

        return $users;
    }

    private function createProperties(ObjectManager $manager, array $users): array
    {
        $alice = $users['alice@host.com'];
        $bob = $users['bob@host.com'];

        $data = [
            [
                'host' => $alice,
                'title' => 'Appartement cosy au cœur de Paris',
                'description' => 'Magnifique appartement haussmannien de 60m² situé dans le 4ème arrondissement. Idéal pour découvrir Paris à pied.',
                'address' => '12 rue de Bretagne',
                'city' => 'Paris',
                'country' => 'France',
                'price' => '120.00',
                'guests' => 3,
                'images' => [
                    'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800',
                    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800',
                ],
            ],
            [
                'host' => $alice,
                'title' => 'Studio vue mer à Nice',
                'description' => 'Charmant studio avec vue panoramique sur la Méditerranée. Terrasse privée, accès direct à la plage.',
                'address' => '8 Promenade des Anglais',
                'city' => 'Nice',
                'country' => 'France',
                'price' => '95.00',
                'guests' => 2,
                'images' => [
                    'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=800',
                ],
            ],
            [
                'host' => $bob,
                'title' => 'Chalet de montagne à Chamonix',
                'description' => 'Authentique chalet en bois avec sauna privatif et vue imprenable sur le Mont-Blanc. Parfait pour skier.',
                'address' => '45 route des Praz',
                'city' => 'Chamonix',
                'country' => 'France',
                'price' => '280.00',
                'guests' => 8,
                'images' => [
                    'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800',
                    'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=800',
                ],
            ],
            [
                'host' => $bob,
                'title' => 'Maison avec piscine à Bordeaux',
                'description' => 'Belle maison bordelaise avec grand jardin et piscine chauffée. Proche des vignobles et du centre-ville.',
                'address' => '23 cours Victor Hugo',
                'city' => 'Bordeaux',
                'country' => 'France',
                'price' => '175.00',
                'guests' => 6,
                'images' => [
                    'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800',
                ],
            ],
            [
                'host' => $alice,
                'title' => 'Loft industriel à Lyon',
                'description' => 'Loft atypique de 80m² dans un ancien entrepôt réhabilité. Décoration design, quartier branché de la Croix-Rousse.',
                'address' => '7 rue de l\'Annonciade',
                'city' => 'Lyon',
                'country' => 'France',
                'price' => '110.00',
                'guests' => 4,
                'images' => [
                    'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800',
                ],
            ],
        ];

        $properties = [];
        foreach ($data as $d) {
            $property = new Property();
            $property->setHost($d['host'])
                ->setTitle($d['title'])
                ->setDescription($d['description'])
                ->setAddress($d['address'])
                ->setCity($d['city'])
                ->setCountry($d['country'])
                ->setPricePerNight($d['price'])
                ->setMaxGuests($d['guests'])
                ->setStatus(PropertyStatus::PUBLISHED);

            foreach ($d['images'] as $url) {
                $img = new PropertyImage();
                $img->setProperty($property)->setImageUrl($url);
                $property->addImage($img);
                $manager->persist($img);
            }

            // First two properties have instant booking; others require host approval
            $property->setInstantBooking(count($properties) < 2);

            // Generate a calendar token for all properties
            $property->generateCalendarToken();

            $manager->persist($property);
            $properties[] = $property;
        }

        return $properties;
    }

    private function createBookings(ObjectManager $manager, array $users, array $properties): array
    {
        $carol = $users['carol@traveler.com'];
        $david = $users['david@traveler.com'];

        $bookingsData = [
            [
                'property' => $properties[0],
                'traveler' => $carol,
                'checkIn' => new \DateTimeImmutable('-30 days'),
                'checkOut' => new \DateTimeImmutable('-25 days'),
                'guests' => 2,
                'status' => BookingStatus::COMPLETED,
            ],
            [
                'property' => $properties[2],
                'traveler' => $carol,
                'checkIn' => new \DateTimeImmutable('+15 days'),
                'checkOut' => new \DateTimeImmutable('+22 days'),
                'guests' => 5,
                'status' => BookingStatus::CONFIRMED,
            ],
            [
                'property' => $properties[1],
                'traveler' => $david,
                'checkIn' => new \DateTimeImmutable('-10 days'),
                'checkOut' => new \DateTimeImmutable('-7 days'),
                'guests' => 2,
                'status' => BookingStatus::COMPLETED,
            ],
            [
                'property' => $properties[3],
                'traveler' => $david,
                'checkIn' => new \DateTimeImmutable('+5 days'),
                'checkOut' => new \DateTimeImmutable('+10 days'),
                'guests' => 4,
                'status' => BookingStatus::PENDING,
            ],
        ];

        $bookings = [];
        foreach ($bookingsData as $d) {
            $nights = $d['checkIn']->diff($d['checkOut'])->days;
            $total = (string) ($nights * (float) $d['property']->getPricePerNight());

            $booking = new Booking();
            $booking->setProperty($d['property'])
                ->setTraveler($d['traveler'])
                ->setCheckIn($d['checkIn'])
                ->setCheckOut($d['checkOut'])
                ->setGuestsCount($d['guests'])
                ->setTotalPrice($total)
                ->setStatus($d['status']);

            $manager->persist($booking);
            $bookings[] = $booking;
        }

        return $bookings;
    }

    private function createReviews(ObjectManager $manager, array $bookings): void
    {
        $completedBookings = array_filter($bookings, fn(Booking $b) => $b->getStatus() === BookingStatus::COMPLETED);

        $comments = [
            'Logement parfait, conforme aux photos. Hôte très réactif et accueillant. Je recommande vivement !',
            'Très bon séjour, appartement propre et bien situé. Nous reviendrons sans hésiter.',
        ];

        $i = 0;
        foreach ($completedBookings as $booking) {
            $review = new Review();
            $review->setBooking($booking)
                ->setReviewer($booking->getTraveler())
                ->setProperty($booking->getProperty())
                ->setRating(rand(4, 5))
                ->setComment($comments[$i % count($comments)]);

            $manager->persist($review);
            $i++;
        }
    }

    private function createAvailabilities(ObjectManager $manager, array $properties): void
    {
        // Block a demo period on the first property (travaux example)
        $period = new PropertyAvailability();
        $period->setProperty($properties[0])
            ->setStartDate(new \DateTimeImmutable('+60 days'))
            ->setEndDate(new \DateTimeImmutable('+70 days'))
            ->setReason('Travaux de rénovation');
        $manager->persist($period);

        // Block a personal use period on the third property
        $period2 = new PropertyAvailability();
        $period2->setProperty($properties[2])
            ->setStartDate(new \DateTimeImmutable('+90 days'))
            ->setEndDate(new \DateTimeImmutable('+97 days'))
            ->setReason('Usage personnel');
        $manager->persist($period2);
    }

    private function createConversations(ObjectManager $manager, array $bookings): void
    {
        foreach (array_slice($bookings, 0, 2) as $booking) {
            $conversation = new Conversation();
            $conversation->setBooking($booking);

            $msg1 = new Message();
            $msg1->setConversation($conversation)
                ->setSender($booking->getTraveler())
                ->setContent('Bonjour, je suis ravi de ma réservation ! Pouvez-vous me donner le code de la porte ?');

            $msg2 = new Message();
            $msg2->setConversation($conversation)
                ->setSender($booking->getProperty()->getHost())
                ->setContent('Bonjour ! Le code est 4521. N\'hésitez pas si vous avez des questions. Bon séjour !');

            $conversation->addMessage($msg1);
            $conversation->addMessage($msg2);

            $manager->persist($conversation);
            $manager->persist($msg1);
            $manager->persist($msg2);
        }
    }
}
