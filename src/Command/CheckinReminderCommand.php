<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ReservationNotification;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Rappel d'arrivée à J-1 (G.2). À planifier une fois par jour (cron). Envoie
 * aux voyageurs dont une réservation confirmée commence le lendemain un e-mail
 * (asynchrone) contenant les informations d'accès, et une notification in-app.
 */
#[AsCommand(
    name: 'app:reservations:checkin-reminder',
    description: 'Envoie les rappels d\'arrivée à J-1 pour les réservations confirmées',
)]
final class CheckinReminderCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly NotificationService $notificationService,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tomorrow = (new \DateTimeImmutable('today'))->modify('+1 day');

        $reservations = $this->reservationRepository->findConfirmedCheckingInOn($tomorrow);

        foreach ($reservations as $reservation) {
            $this->notificationService->notifyReservation($reservation, ReservationNotification::EVENT_CHECKIN_REMINDER);
            $this->messageBus->dispatch(new ReservationNotification(
                (string) $reservation->getId(),
                ReservationNotification::EVENT_CHECKIN_REMINDER,
            ));
            $io->writeln(sprintf('  • Rappel envoyé pour la réservation %s', $reservation->getId()));
        }

        $io->success(sprintf('%d rappel(s) d\'arrivée programmé(s) pour le %s.', count($reservations), $tomorrow->format('d/m/Y')));

        return Command::SUCCESS;
    }
}
