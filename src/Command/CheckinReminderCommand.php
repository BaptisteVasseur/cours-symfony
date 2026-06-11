<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\CheckinReminderNotification;
use App\Repository\ReservationRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Envoie un rappel d'arrivée pour les séjours confirmés débutant le lendemain
 * (énoncé §G.2). Le mail part de façon asynchrone (Messenger). Conçue pour le Cron
 * (exécution quotidienne).
 */
#[AsCommand(
    name: 'app:reservations:checkin-reminder',
    description: 'Envoie le rappel de check-in (J-1) aux séjours confirmés.',
)]
final class CheckinReminderCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MessageBusInterface   $messageBus,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tomorrow = (new DateTimeImmutable('tomorrow'))->setTime(0, 0);

        $reservations = $this->reservationRepository->findConfirmedWithCheckinOn($tomorrow);
        foreach ($reservations as $reservation) {
            $this->messageBus->dispatch(new CheckinReminderNotification((string)$reservation->getId()));
        }

        $io->success(sprintf('%d rappel(s) de check-in programmé(s) pour le %s.', count($reservations), $tomorrow->format('d/m/Y')));

        return Command::SUCCESS;
    }
}
