<?php

namespace App\Command;

use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\BookingCancelledMessage;

/**
 * G.1 Bonus — auto-expire PENDING bookings older than 24h.
 *
 * Run via cron:
 *   0 * * * * docker exec php php bin/console app:expire-bookings
 */
#[AsCommand(
    name: 'app:expire-bookings',
    description: 'Annule automatiquement les demandes PENDING non traitées après 24h.',
)]
class ExpireBookingsCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expired = $this->bookingRepo->findExpiredPending();

        if (empty($expired)) {
            $io->success('Aucune réservation expirée à traiter.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $booking) {
            $booking->setStatus(BookingStatus::CANCELLED);
            $booking->setCancellationReason('Annulation automatique : demande non traitée dans les 24h.');
            $this->bus->dispatch(new BookingCancelledMessage((string) $booking->getId()));
            $count++;
        }

        $this->em->flush();

        $io->success(sprintf('%d réservation(s) expirée(s) annulée(s).', $count));

        return Command::SUCCESS;
    }
}
