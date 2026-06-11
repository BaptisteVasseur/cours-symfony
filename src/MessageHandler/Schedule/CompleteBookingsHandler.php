<?php

declare(strict_types=1);

namespace App\MessageHandler\Schedule;

use App\Message\Schedule\CompleteBookingsMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CompleteBookingsHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(CompleteBookingsMessage $message): void
    {
        $toComplete = $this->reservationRepository->findConfirmedToComplete();

        foreach ($toComplete as $reservation) {
            $reservation->setStatus('completed');
            $reservation->setUpdatedAt(new \DateTimeImmutable());
        }

        if (count($toComplete) > 0) {
            $this->em->flush();
        }
    }
}
