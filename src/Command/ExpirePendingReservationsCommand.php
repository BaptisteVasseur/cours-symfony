<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\BookingStatusChangedMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:reservation:expire-pending',
    description: 'Annule les réservations en attente non confirmées après le délai configuré',
)]
final class ExpirePendingReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'delay',
            null,
            InputOption::VALUE_REQUIRED,
            'Délai en heures avant expiration',
            '24',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $delayHours = max(0, (int) $input->getOption('delay'));
        $before = new \DateTimeImmutable(sprintf('-%d hours', $delayHours));

        $reservations = $this->reservationRepository->findExpiredPending($before);
        $expiredIds = [];

        foreach ($reservations as $reservation) {
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason('Réservation expirée (non confirmée à temps)');
            $expiredIds[] = (string) $reservation->getId();
        }

        $this->entityManager->flush();

        foreach ($expiredIds as $reservationId) {
            $this->bus->dispatch(new BookingStatusChangedMessage($reservationId));
        }

        $count = count($expiredIds);
        $io->success(sprintf('%d réservation(s) expirée(s) (délai : %d h).', $count, $delayHours));

        return Command::SUCCESS;
    }
}
