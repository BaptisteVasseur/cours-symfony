<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReservationFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $guest1 = $this->getReference(FixtureReferences::USER_GUEST_1, User::class);
        $guest2 = $this->getReference(FixtureReferences::USER_GUEST_2, User::class);
        $villa = $this->getReference(FixtureReferences::PROPERTY_1, Property::class); // Villa Luxe
        $loft = $this->getReference(FixtureReferences::PROPERTY_2, Property::class);  // Loft Santorini
        $chalet = $this->getReference(FixtureReferences::PROPERTY_3, Property::class); // Chalet Alpin

        // 1. Une réservation CONFIRMÉE (pour l'iCal du Chalet)
        $res1 = new Reservation();
        $res1->setProperty($chalet);
        $res1->setGuest($guest1);
        $res1->setCheckinDate(new \DateTimeImmutable('now + 2 days'));
        $res1->setCheckoutDate(new \DateTimeImmutable('now + 7 days'));
        $res1->setGuestsCount(2);
        $res1->setStatus('confirmed');
        $res1->setTotalPrice('850.00');
        $res1->setCurrency('EUR');
        $manager->persist($res1);
        
        // Ajout des références attendues par tes autres fixtures
        $this->addReference('reservation_confirmed', $res1);

        // 2. Une réservation EN ATTENTE (pour le Dashboard Hôte)
        $res2 = new Reservation();
        $res2->setProperty($villa);
        $res2->setGuest($guest2);
        $res2->setCheckinDate(new \DateTimeImmutable('now + 10 days'));
        $res2->setCheckoutDate(new \DateTimeImmutable('now + 12 days'));
        $res2->setGuestsCount(3);
        $res2->setStatus('pending');
        $res2->setTotalPrice('420.00');
        $res2->setCurrency('EUR');
        $manager->persist($res2);
        
        $this->addReference('reservation_pending', $res2);

        // 3. Une réservation annulée
        $res3 = new Reservation();
        $res3->setProperty($loft);
        $res3->setGuest($guest1);
        $res3->setCheckinDate(new \DateTimeImmutable('now - 5 days'));
        $res3->setCheckoutDate(new \DateTimeImmutable('now - 2 days'));
        $res3->setGuestsCount(1);
        $res3->setStatus('cancelled');
        $res3->setTotalPrice('300.00');
        $res3->setCurrency('EUR');
        $manager->persist($res3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PropertyFixture::class, UserFixture::class];
    }
}
