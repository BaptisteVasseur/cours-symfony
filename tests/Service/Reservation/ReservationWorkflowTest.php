<?php

declare(strict_types=1);

namespace App\Tests\Service\Reservation;

use App\Enum\ReservationNotificationType;
use App\Message\ReservationNotification;
use App\Service\Availability\AvailabilityChecker;
use App\Service\Availability\AvailabilityFailureReason;
use App\Service\Availability\Exception\PropertyNotAvailableException;
use App\Service\Reservation\Exception\InvalidReservationTransitionException;
use App\Service\Reservation\ReservationWorkflow;
use App\Tests\Support\ReservationFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReservationWorkflowTest extends KernelTestCase
{
    use ReservationFactoryTrait;

    private EntityManagerInterface $em;
    private ReservationWorkflow $workflow;
    private AvailabilityChecker $availability;
    private \DateTimeImmutable $base;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->workflow = $container->get(ReservationWorkflow::class);
        $this->availability = $container->get(AvailabilityChecker::class);

        $this->em->getConnection()->beginTransaction();
        $this->base = (new \DateTimeImmutable('today'))->modify('+10 days');
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        $this->em->close();
        parent::tearDown();
    }

    public function testConfirmConfirmeHistoriseEtNotifie(): void
    {
        $property = $this->makePublishedProperty($this->em);
        $guest = $this->makeUser($this->em, 'guest-' . uniqid('', true) . '@test.local');
        $reservation = $this->makeReservation($this->em, $property, $guest, $this->day(0), $this->day(5), 'pending');

        $before = count($this->sentMessages());
        $this->workflow->confirm($reservation, $property->getHost());

        self::assertSame('confirmed', $reservation->getStatus());

        $transitions = array_filter(
            $reservation->getStatusHistory()->toArray(),
            static fn ($h): bool => $h->getOldStatus() === 'pending' && $h->getNewStatus() === 'confirmed',
        );
        self::assertCount(1, $transitions);

        $sent = $this->sentMessages();
        self::assertCount($before + 1, $sent);
        $message = end($sent)->getMessage();
        self::assertInstanceOf(ReservationNotification::class, $message);
        self::assertSame(ReservationNotificationType::ACCEPTED, $message->type);
    }

    public function testConfirmRefuseSiDatesPlusLibres(): void
    {
        $property = $this->makePublishedProperty($this->em);
        $guestA = $this->makeUser($this->em, 'a-' . uniqid('', true) . '@test.local');
        $guestB = $this->makeUser($this->em, 'b-' . uniqid('', true) . '@test.local');
        $this->makeReservation($this->em, $property, $guestA, $this->day(0), $this->day(5), 'confirmed');
        $pending = $this->makeReservation($this->em, $property, $guestB, $this->day(0), $this->day(5), 'pending');

        $this->expectException(PropertyNotAvailableException::class);
        $this->workflow->confirm($pending, $property->getHost());
    }

    public function testRefuseAnnuleAvecMotifEtNotifie(): void
    {
        $property = $this->makePublishedProperty($this->em);
        $guest = $this->makeUser($this->em, 'g-' . uniqid('', true) . '@test.local');
        $reservation = $this->makeReservation($this->em, $property, $guest, $this->day(0), $this->day(5), 'pending');

        $before = count($this->sentMessages());
        $this->workflow->refuse($reservation, $property->getHost(), 'Indisponible finalement');

        self::assertSame('cancelled', $reservation->getStatus());
        self::assertSame('Indisponible finalement', $reservation->getCancellationReason());

        $sent = $this->sentMessages();
        self::assertCount($before + 1, $sent);
        self::assertSame(ReservationNotificationType::REFUSED, end($sent)->getMessage()->type);
    }

    public function testCancelLibereLesDates(): void
    {
        $property = $this->makePublishedProperty($this->em);
        $guest = $this->makeUser($this->em, 'g-' . uniqid('', true) . '@test.local');
        $reservation = $this->makeReservation($this->em, $property, $guest, $this->day(0), $this->day(5), 'confirmed');

        self::assertSame(
            AvailabilityFailureReason::CHEVAUCHEMENT,
            $this->availability->check($property, $this->day(0), $this->day(5), 2)->getReason(),
        );

        $this->workflow->cancel($reservation, $guest, 'Imprévu');

        self::assertSame('cancelled', $reservation->getStatus());
        self::assertTrue($this->availability->check($property, $this->day(0), $this->day(5), 2)->isAvailable());
    }

    public function testTransitionsInterditesRejetees(): void
    {
        $property = $this->makePublishedProperty($this->em);
        $guest = $this->makeUser($this->em, 'g-' . uniqid('', true) . '@test.local');
        $host = $property->getHost();

        $cancelled = $this->makeReservation($this->em, $property, $guest, $this->day(0), $this->day(5), 'cancelled');
        $completed = $this->makeReservation($this->em, $property, $guest, $this->day(10), $this->day(12), 'completed');
        $confirmed = $this->makeReservation($this->em, $property, $guest, $this->day(20), $this->day(22), 'confirmed');

        $this->assertRejected(fn () => $this->workflow->confirm($cancelled, $host));   // cancelled → confirmed
        $this->assertRejected(fn () => $this->workflow->cancel($completed, $guest, 'x')); // completed → cancelled
        $this->assertRejected(fn () => $this->workflow->refuse($confirmed, $host, 'x'));  // refuse exige pending
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            self::fail('Une InvalidReservationTransitionException était attendue.');
        } catch (InvalidReservationTransitionException) {
            self::assertTrue(true);
        }
    }

    private function day(int $offset): \DateTimeImmutable
    {
        return $this->base->modify(sprintf('%+d days', $offset));
    }

    /**
     * @return list<\Symfony\Component\Messenger\Envelope>
     */
    private function sentMessages(): array
    {
        return static::getContainer()->get('messenger.transport.async')->getSent();
    }
}
