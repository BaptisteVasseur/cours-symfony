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

#[AsCommand(name: 'app:reservations:expire', description: 'Cancel pending reservations older than 24h')]
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

        $reservations = $this->reservationRepository->findExpiredPending();
        $count = 0;

        foreach ($reservations as $reservation) {
            $history = new ReservationStatusHistory();
            $history->setReservation($reservation);
            $history->setOldStatus($reservation->getStatus());
            $history->setNewStatus('cancelled');

            $systemUser = $reservation->getGuest();
            $history->setChangedBy($systemUser);

            $reservation->setStatus('cancelled');
            $reservation->setCancellationReason('Expirée automatiquement après 24h sans réponse de l\'hôte.');

            $this->em->persist($history);

            ++$count;
        }

        if ($count > 0) {
            $this->em->flush();

            foreach ($reservations as $reservation) {
                $this->bus->dispatch(new ReservationCancelledMessage((string) $reservation->getId()));
            }
        }

        $io->success(sprintf('%d réservation(s) expirée(s) annulée(s).', $count));

        return Command::SUCCESS;
    }
}
