<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
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
        $reservedDates = [];

        $getDatesRange = static function(\DateTimeImmutable $start, \DateTimeImmutable $end): array {
            $dates = [];
            $period = new \DatePeriod(
                $start,
                new \DateInterval('P1D'),
                $end
            );
            foreach ($period as $date) {
                $dates[] = $date->format('Y-m-d');
            }
            return $dates;
        };

        // Enregistrer les 4 de base
        foreach ($reservations as [$reference, $property, $guest, $checkin, $checkout, $guestsCount, $status, $totalPrice, $cancellationReason]) {
            $checkinDate = new \DateTimeImmutable($checkin);
            $checkoutDate = new \DateTimeImmutable($checkout);

            $reservation = new Reservation();
            $reservation->setProperty($property);
            $reservation->setGuest($guest);
            $reservation->setCheckinDate($checkinDate);
            $reservation->setCheckoutDate($checkoutDate);
            $reservation->setGuestsCount($guestsCount);
            $reservation->setStatus($status);
            $reservation->setTotalPrice($totalPrice);
            $reservation->setCleaningFee('45.00');
            $reservation->setServiceFee('35.00');
            $reservation->setSecurityDeposit('200.00');
            $reservation->setCurrency('EUR');
            $reservation->setCancellationReason($cancellationReason);
            $manager->persist($reservation);

            // Remplir le registre
            $propId = (string) $property->getId();
            if ($status !== 'cancelled') {
                $datesRange = $getDatesRange($checkinDate, $checkoutDate);
                foreach ($datesRange as $dStr) {
                    $reservedDates[$propId][] = $dStr;
                }
            }

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus(null);
            $history->setNewStatus('pending');
            $history->setChangedBy($guest);
            $manager->persist($history);

            if ($status !== 'pending') {
                $historyConfirmed = new ReservationStatusHistory();
                $historyConfirmed->setReservation($reservation);
                $historyConfirmed->setOldStatus('pending');
                $historyConfirmed->setNewStatus($status);
                $historyConfirmed->setChangedBy($admin);
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

        // Récupérer toutes les propriétés et tous les utilisateurs voyageurs
        $allProperties = $manager->getRepository(Property::class)->findAll();
        $allGuests = array_filter(
            $manager->getRepository(User::class)->findAll(),
            static fn (User $u): bool => !in_array('ROLE_ADMIN', $u->getRoles(), true) && !in_array('ROLE_SUPER_ADMIN', $u->getRoles(), true)
        );
        $allGuests = array_values($allGuests);

        if (count($allProperties) > 0 && count($allGuests) > 0) {
            $isRangeAvailable = static function(?string $propId, array $range, array $reservedDates): bool {
                if ($propId === null || !isset($reservedDates[$propId])) {
                    return true;
                }
                foreach ($range as $date) {
                    if (in_array($date, $reservedDates[$propId], true)) {
                        return false;
                    }
                }
                return true;
            };

            for ($k = 0; $k < 100; $k++) {
                // Trouver une propriété et un guest au hasard
                /** @var Property $property */
                $property = $allProperties[array_rand($allProperties)];
                /** @var User $guest */
                $guest = $allGuests[array_rand($allGuests)];

                // Essayer de trouver une date libre
                $success = false;
                $checkinDate = null;
                $checkoutDate = null;

                for ($attempt = 0; $attempt < 15; $attempt++) {
                    // Aléatoire entre -30 jours et +60 jours
                    $offset = random_int(-30, 60);
                    $nights = random_int(2, 6);

                    $tempCheckin = (new \DateTimeImmutable('today'))->modify(sprintf('%+d days', $offset));
                    $tempCheckout = $tempCheckin->modify(sprintf('+%d days', $nights));

                    $range = $getDatesRange($tempCheckin, $tempCheckout);
                    if ($isRangeAvailable((string) $property->getId(), $range, $reservedDates)) {
                        $checkinDate = $tempCheckin;
                        $checkoutDate = $tempCheckout;
                        $success = true;
                        
                        // Enregistrer les dates
                        $propId = (string) $property->getId();
                        foreach ($range as $dStr) {
                            $reservedDates[$propId][] = $dStr;
                        }
                        break;
                    }
                }

                if (!$success) {
                    continue; // impossible de caser une date, on passe à la suivante
                }

                $status = ['pending', 'confirmed', 'completed', 'cancelled'][random_int(0, 3)];
                $guestsCount = random_int(1, max(1, $property->getMaxGuests() ?? 2));
                
                $nightsCount = $checkinDate->diff($checkoutDate)->days;
                $pricePerNight = floatval($property->getPricePerNight() ?? 100);
                $cleaningFee = floatval($property->getCleaningFee() ?? 30);
                
                $nightsTotal = $pricePerNight * $nightsCount;
                $serviceFee = $nightsTotal * 0.10;
                $securityDeposit = floatval($property->getSecurityDeposit() ?? 150);
                $totalAmount = $nightsTotal + $cleaningFee + $serviceFee;

                $reservation = new Reservation();
                $reservation->setProperty($property);
                $reservation->setGuest($guest);
                $reservation->setCheckinDate($checkinDate);
                $reservation->setCheckoutDate($checkoutDate);
                $reservation->setGuestsCount($guestsCount);
                $reservation->setStatus($status);
                $reservation->setTotalPrice(number_format($totalAmount, 2, '.', ''));
                $reservation->setCleaningFee(number_format($cleaningFee, 2, '.', ''));
                $reservation->setServiceFee(number_format($serviceFee, 2, '.', ''));
                $reservation->setSecurityDeposit(number_format($securityDeposit, 2, '.', ''));
                $reservation->setCurrency('EUR');

                if ($status === 'cancelled') {
                    $reservation->setCancellationReason('Annulation de test');
                }

                $manager->persist($reservation);

                // Historique
                $history = new ReservationStatusHistory();
                $history->setReservation($reservation);
                $history->setOldStatus(null);
                $history->setNewStatus('pending');
                $history->setChangedBy($guest);
                $manager->persist($history);

                if ($status !== 'pending') {
                    $historyConfirmed = new ReservationStatusHistory();
                    $historyConfirmed->setReservation($reservation);
                    $historyConfirmed->setOldStatus('pending');
                    $historyConfirmed->setNewStatus($status);
                    $historyConfirmed->setChangedBy($admin);
                    $manager->persist($historyConfirmed);
                }

                // Facture
                if (in_array($status, ['confirmed', 'completed'], true)) {
                    $invoice = new Invoice();
                    $invoice->setReservation($reservation);
                    $invoice->setInvoiceNumber(sprintf('INV-2026-%05d', $invoiceCounter++));
                    $invoice->setPdfUrl('https://storage.example.com/invoices/rand_' . $k . '_' . md5((string) $k) . '.pdf');
                    $invoice->setTotalAmount(number_format($totalAmount, 2, '.', ''));
                    $reservation->setInvoice($invoice);
                    $manager->persist($invoice);
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PropertyFixture::class, UserFixture::class];
    }
}
