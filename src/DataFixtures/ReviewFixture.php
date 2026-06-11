<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\BookingStatusHistory;
use App\Entity\Review;
use App\Enum\BookingStatus;
use App\Entity\ReviewMedia;
use App\Entity\ReviewReport;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReviewFixture extends Fixture implements DependentFixtureInterface
{
    /** @var list<string> */
    private const array GUEST_COMMENTS = [
        'Séjour impeccable, logement conforme aux photos.',
        'Très bon accueil, quartier calme et bien desservi.',
        'Appartement propre et fonctionnel, je recommande.',
        'Vue magnifique, nous reviendrons avec plaisir.',
        'Communication fluide avec l\'hôte du début à la fin.',
        'Literie confortable et équipements complets.',
        'Parfait pour un week-end en famille.',
        'Emplacement idéal, tout se fait à pied.',
        'Rapport qualité-prix excellent.',
        'Petit déjeuner sur la terrasse inoubliable.',
        'Logement spacieux et bien décoré.',
        'Check-in simple, instructions claires.',
    ];

    /** @var list<string> */
    private const array CONFIRMED_COMMENTS = [
        'Réservation confirmée, hâte de découvrir le logement.',
        'Échanges très agréables avant l\'arrivée.',
        'Tout est prêt pour notre séjour, merci à l\'hôte.',
    ];

    /** @var list<string> */
    private const array HOST_COMMENTS = [
        'Voyageur respectueux, logement rendu en parfait état.',
        'Excellente communication, accueil sans souci.',
        'Séjour sans incident, je recommande ce voyageur.',
    ];

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(FixtureReferences::USER_ADMIN, User::class);
        $firstReview = null;

        /** @var list<Reservation> $reservations */
        $reservations = $manager->getRepository(Reservation::class)->findAll();

        foreach ($reservations as $reservation) {
            $status = $reservation->getStatus();
            if (!in_array($status, ['completed', 'confirmed'], true)) {
                continue;
            }

            $guest = $reservation->getGuest();
            $property = $reservation->getProperty();
            $host = $property?->getHost();

            if ($guest === null || $property === null || $host === null) {
                continue;
            }

            if (!$this->hasReviewFromReviewer($manager, $reservation, $guest)) {
                $review = $this->createGuestReview($reservation, $guest, $host, $property, $status);
                $manager->persist($review);

                if ($firstReview === null) {
                    $firstReview = $review;
                }
            }

            if ($status === 'completed' && !$this->hasReviewFromReviewer($manager, $reservation, $host)) {
                $hostReview = new Review();
                $hostReview->setReservation($reservation);
                $hostReview->setReviewer($host);
                $hostReview->setReviewedUser($guest);
                $hostReview->setProperty($property);
                $hostReview->setRating(random_int(4, 5));
                $hostReview->setComment(self::HOST_COMMENTS[array_rand(self::HOST_COMMENTS)]);
                $manager->persist($hostReview);
            }
        }

        if ($firstReview !== null) {
            $this->addReference(FixtureReferences::REVIEW_1, $firstReview);

            $media = new ReviewMedia();
            $media->setReview($firstReview);
            $media->setFileUrl(FixtureImageProvider::forReview($firstReview->getProperty()?->getTitle() ?? 'review'));
            $manager->persist($media);

            $report = new ReviewReport();
            $report->setReview($firstReview);
            $report->setReportedBy($this->getReference(FixtureReferences::USER_GUEST_3, User::class));
            $report->setReason('Contenu inapproprié signalé par erreur');
            $report->setStatus('dismissed');
            $manager->persist($report);
        }

        /** @var list<User> $extraGuests */
        $extraGuests = $manager->getRepository(User::class)->createQueryBuilder('u')
            ->andWhere('u.email LIKE :pattern')
            ->setParameter('pattern', 'guest%@example.com')
            ->getQuery()
            ->getResult();

        /** @var list<Property> $properties */
        $properties = $manager->getRepository(Property::class)->findBy(['status' => 'published']);

        foreach ($properties as $property) {
            $host = $property->getHost();
            if ($host === null || $extraGuests === []) {
                continue;
            }

            $currentCount = (int) $manager->getRepository(Review::class)->count(['property' => $property]);
            $target = random_int(2, 10);
            $needed = max(0, $target - $currentCount);

            for ($i = 0; $i < $needed; $i++) {
                $guest = $extraGuests[($currentCount + $i) % count($extraGuests)];
                $daysAgo = 60 + ($currentCount + $i) * 7;

                $reservation = new Reservation();
                $reservation->setProperty($property);
                $reservation->setGuest($guest);
                $reservation->setHost($property->getHost());
                $reservation->setUpdatedAt(new \DateTimeImmutable());
                $reservation->setCheckinDate(new \DateTimeImmutable(sprintf('-%d days', $daysAgo + 3)));
                $reservation->setCheckoutDate(new \DateTimeImmutable(sprintf('-%d days', $daysAgo)));
                $reservation->setGuestsCount(random_int(1, min(4, $property->getMaxGuests() ?? 4)));
                $reservation->setStatus('completed');
                $reservation->setTotalPrice((string) random_int(180, 1200));
                $reservation->setCleaningFee('45.00');
                $reservation->setServiceFee('35.00');
                $reservation->setSecurityDeposit('200.00');
                $reservation->setCurrency('EUR');
                $manager->persist($reservation);

                $history = new BookingStatusHistory();
                $history->setBooking($reservation);
                $history->setFromStatus(null);
                $history->setToStatus(BookingStatus::COMPLETED);
                $history->setActor('system');
                $manager->persist($history);

                $review = $this->createGuestReview($reservation, $guest, $host, $property, 'completed');
                $review->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', $daysAgo - 1)));
                $manager->persist($review);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ReservationFixture::class,
            TestAccountFixture::class,
            UserFixture::class,
        ];
    }

    private function hasReviewFromReviewer(ObjectManager $manager, Reservation $reservation, User $reviewer): bool
    {
        return $manager->getRepository(Review::class)->count([
            'reservation' => $reservation,
            'reviewer' => $reviewer,
        ]) > 0;
    }

    private function createGuestReview(
        Reservation $reservation,
        User $guest,
        User $host,
        Property $property,
        string $status,
    ): Review {
        $comments = $status === 'confirmed' ? self::CONFIRMED_COMMENTS : self::GUEST_COMMENTS;

        $review = new Review();
        $review->setReservation($reservation);
        $review->setReviewer($guest);
        $review->setReviewedUser($host);
        $review->setProperty($property);
        $review->setRating($status === 'confirmed' ? random_int(4, 5) : random_int(3, 5));
        $review->setComment($comments[array_rand($comments)]);

        return $review;
    }
}
