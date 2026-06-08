<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\ReviewMedia;
use App\Entity\ReviewReport;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReviewFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $completed = $this->getReference(FixtureReferences::RESERVATION_COMPLETED, Reservation::class);
        $guest = $this->getReference(FixtureReferences::USER_GUEST_2, User::class);
        $host = $this->getReference(FixtureReferences::USER_HOST_1, User::class);
        $property = $completed->getProperty();

        $review = new Review();
        $review->setReservation($completed);
        $review->setReviewer($guest);
        $review->setReviewedUser($host);
        $review->setProperty($property);
        $review->setRating(5);
        $review->setComment('Appartement impeccable, hôte très réactif. Je recommande vivement !');
        $manager->persist($review);
        $this->addReference(FixtureReferences::REVIEW_1, $review);

        $media = new ReviewMedia();
        $media->setReview($review);
        $media->setFileUrl(FixtureImageProvider::forReview($property->getTitle() ?? 'review'));
        $manager->persist($media);

        $hostReview = new Review();
        $hostReview->setReservation($completed);
        $hostReview->setReviewer($host);
        $hostReview->setReviewedUser($guest);
        $hostReview->setProperty($property);
        $hostReview->setRating(5);
        $hostReview->setComment('Voyageur respectueux, logement rendu en parfait état.');
        $manager->persist($hostReview);

        $report = new ReviewReport();
        $report->setReview($review);
        $report->setReportedBy($this->getReference(FixtureReferences::USER_GUEST_3, User::class));
        $report->setReason('Contenu inapproprié signalé par erreur');
        $report->setStatus('dismissed');
        $manager->persist($report);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ReservationFixture::class, UserFixture::class];
    }
}
