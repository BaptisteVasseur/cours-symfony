<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ReservationStatusHistory;
use App\Message\ReservationCancelledMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:reservations:expire',
    description: 'Cancels pending reservations that have not been acted on within 24 hours.',
)]
final class ExpirePendingReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = new \DateTimeImmutable('-24 hours');
        $pending = $this->reservationRepository->findExpiredPending($threshold);

        if (empty($pending)) {
            $io->success('No pending reservations to expire.');
            return Command::SUCCESS;
        }

        $reason = 'Annulation automatique : la demande n\'a pas reçu de réponse dans les 24 heures.';
        $count = 0;

        foreach ($pending as $reservation) {
            $oldStatus = $reservation->getStatus();
            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason($reason);

            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus($oldStatus);
            $history->setNewStatus('cancelled');
            // System-triggered — no human actor, use the guest as reference
            $history->setChangedBy($reservation->getGuest());
            $this->em->persist($history);

            ++$count;
        }

        $this->em->flush();

        // Dispatch notifications after flush
        foreach ($pending as $reservation) {
            $this->bus->dispatch(new ReservationCancelledMessage(
                (string) $reservation->getId(),
                $reason,
            ));
        }

        $io->success(sprintf('Expired %d pending reservation(s).', $count));

        return Command::SUCCESS;
    }
}
