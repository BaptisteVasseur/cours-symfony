<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Payment;
use App\Entity\Payout;
use App\Entity\Refund;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $confirmed = $this->getReference(FixtureReferences::RESERVATION_CONFIRMED, Reservation::class);
        $completed = $this->getReference(FixtureReferences::RESERVATION_COMPLETED, Reservation::class);
        $cancelled = $this->getReference(FixtureReferences::RESERVATION_CANCELLED, Reservation::class);

        $guest1 = $this->getReference(FixtureReferences::USER_GUEST_1, User::class);
        $guest2 = $this->getReference(FixtureReferences::USER_GUEST_2, User::class);
        $guest3 = $this->getReference(FixtureReferences::USER_GUEST_3, User::class);
        $host1 = $this->getReference(FixtureReferences::USER_HOST_1, User::class);
        $host2 = $this->getReference(FixtureReferences::USER_HOST_2, User::class);

        $paymentConfirmed = new Payment();
        $paymentConfirmed->setReservation($confirmed);
        $paymentConfirmed->setPayer($guest1);
        $paymentConfirmed->setProvider('stripe');
        $paymentConfirmed->setProviderPaymentIntent('pi_confirmed_demo_001');
        $paymentConfirmed->setAmount('840.00');
        $paymentConfirmed->setCurrency('EUR');
        $paymentConfirmed->setStatus('succeeded');
        $paymentConfirmed->setPaidAt(new \DateTimeImmutable('-1 day'));
        $manager->persist($paymentConfirmed);
        $this->addReference(FixtureReferences::PAYMENT_CONFIRMED, $paymentConfirmed);

        $payoutConfirmed = new Payout();
        $payoutConfirmed->setHost($host2);
        $payoutConfirmed->setReservation($confirmed);
        $payoutConfirmed->setAmount('672.00');
        $payoutConfirmed->setCurrency('EUR');
        $payoutConfirmed->setStatus('pending');
        $manager->persist($payoutConfirmed);

        $paymentCompleted = new Payment();
        $paymentCompleted->setReservation($completed);
        $paymentCompleted->setPayer($guest2);
        $paymentCompleted->setProvider('stripe');
        $paymentCompleted->setProviderPaymentIntent('pi_completed_demo_002');
        $paymentCompleted->setAmount('267.00');
        $paymentCompleted->setCurrency('EUR');
        $paymentCompleted->setStatus('succeeded');
        $paymentCompleted->setPaidAt(new \DateTimeImmutable('-28 days'));
        $manager->persist($paymentCompleted);

        $payoutCompleted = new Payout();
        $payoutCompleted->setHost($host1);
        $payoutCompleted->setReservation($completed);
        $payoutCompleted->setAmount('213.60');
        $payoutCompleted->setCurrency('EUR');
        $payoutCompleted->setStatus('paid');
        $payoutCompleted->setPaidAt(new \DateTimeImmutable('-25 days'));
        $manager->persist($payoutCompleted);

        $paymentCancelled = new Payment();
        $paymentCancelled->setReservation($cancelled);
        $paymentCancelled->setPayer($guest3);
        $paymentCancelled->setProvider('stripe');
        $paymentCancelled->setProviderPaymentIntent('pi_cancelled_demo_003');
        $paymentCancelled->setAmount('840.00');
        $paymentCancelled->setCurrency('EUR');
        $paymentCancelled->setStatus('refunded');
        $paymentCancelled->setPaidAt(new \DateTimeImmutable('-3 days'));
        $manager->persist($paymentCancelled);

        $refund = new Refund();
        $refund->setPayment($paymentCancelled);
        $refund->setAmount('420.00');
        $refund->setReason('Annulation partielle selon politique modérée');
        $refund->setStatus('succeeded');
        $manager->persist($refund);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ReservationFixture::class, UserFixture::class];
    }
}
