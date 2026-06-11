<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ReservationStatusHistory;
use App\Message\BookingCancelledMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:bookings:expire-pending',
    description: 'Annule automatiquement les demandes pending non traitées après 24h.',
)]
final class ExpirePendingBookingsCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new \DateTimeImmutable('-24 hours');

        $expired = $this->reservationRepository->findExpiredPending($threshold);

        if (empty($expired)) {
            $io->success('Aucune réservation expirée.');

            return Command::SUCCESS;
        }

        foreach ($expired as $reservation) {
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason('Demande expirée après 24h sans réponse de l\'hôte.');

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus('pending');
            $history->setNewStatus('cancelled');
            $this->entityManager->persist($history);

            $this->bus->dispatch(new BookingCancelledMessage(
                $reservation->getId(),
                'Demande expirée automatiquement après 24h.',
                'système',
            ));
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d réservation(s) expirée(s) annulée(s).', count($expired)));

        return Command::SUCCESS;
    }
}
