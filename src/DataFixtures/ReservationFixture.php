<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Enum\BookingStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReservationFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $property1 = $this->getReference(FixtureReferences::PROPERTY_1, Property::class);
        $property2 = $this->getReference(FixtureReferences::PROPERTY_2, Property::class);
        $property3 = $this->getReference(FixtureReferences::PROPERTY_3, Property::class);

        $guest1 = $this->getReference(FixtureReferences::USER_GUEST_1, User::class);
        $guest2 = $this->getReference(FixtureReferences::USER_GUEST_2, User::class);
        $guest3 = $this->getReference(FixtureReferences::USER_GUEST_3, User::class);
        $admin = $this->getReference(FixtureReferences::USER_ADMIN, User::class);

        $reservations = [
            [
                FixtureReferences::RESERVATION_CONFIRMED,
                $property2,
                $guest1,
                '+14 days',
                '+17 days',
                2,
                'confirmed',
                '840.00',
                null,
            ],
            [
                FixtureReferences::RESERVATION_COMPLETED,
                $property3,
                $guest2,
                '-30 days',
                '-27 days',
                1,
                'completed',
                '267.00',
                null,
            ],
            [
                FixtureReferences::RESERVATION_PENDING,
                $property1,
                $guest2,
                '+7 days',
                '+10 days',
                4,
                'pending',
                '435.00',
                null,
            ],
            [
                FixtureReferences::RESERVATION_CANCELLED,
                $property2,
                $guest3,
                '+21 days',
                '+24 days',
                2,
                'cancelled',
                '840.00',
                'Changement de programme personnel',
            ],
        ];

        $invoiceCounter = 1;

        foreach ($reservations as [$reference, $property, $guest, $checkin, $checkout, $guestsCount, $status, $totalPrice, $cancellationReason]) {
            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setHost($property->getHost());
            $reservation->setUpdatedAt(new \DateTimeImmutable());
            $reservation->setCheckinDate(new \DateTimeImmutable($checkin));
            $reservation->setCheckoutDate(new \DateTimeImmutable($checkout));
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($status);
            $reservation->setTotalPrice($totalPrice);
            $reservation->setCleaningFee('45.00');
            $reservation->setServiceFee('35.00');
            $reservation->setSecurityDeposit('200.00');
            $reservation->setCurrency('EUR');
            $reservation->setCancellationReason($cancellationReason);
            $manager->persist($reservation);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setFromStatus(null);
            $history->setToStatus(BookingStatus::PENDING);
            $history->setActor('guest');
            $manager->persist($history);

            if ($status !== 'pending') {
                $historyConfirmed = new ReservationStatusHistory();
                $historyConfirmed->setReservation($reservation);
                $historyConfirmed->setFromStatus(BookingStatus::PENDING);
                $historyConfirmed->setToStatus(BookingStatus::from($status));
                $historyConfirmed->setActor($status === 'cancelled' ? 'guest' : 'host');
                $manager->persist($historyConfirmed);
            }

            if (in_array($status, ['confirmed', 'completed'], true)) {
                $invoice = new Invoice();
                $invoice->setReservation($reservation);
                $invoice->setInvoiceNumber(sprintf('INV-2026-%05d', $invoiceCounter++));
                $invoice->setPdfUrl('https://storage.example.com/invoices/' . md5((string) $reference) . '.pdf');
                $invoice->setTotalAmount($totalPrice);
                $reservation->setInvoice($invoice);
                $manager->persist($invoice);
            }

            $this->addReference($reference, $reservation);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PropertyFixture::class, UserFixture::class];
    }
}
