<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\CheckinReminderMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:checkin:remind', description: 'Envoie un rappel J-1 aux voyageurs dont le check-in est demain.')]
final class SendCheckinReminderCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $reservations = $this->reservationRepository->findCheckinTomorrow();

        if (empty($reservations)) {
            $io->info('Aucun check-in prévu demain.');

            return Command::SUCCESS;
        }

        foreach ($reservations as $reservation) {
            $this->bus->dispatch(new CheckinReminderMessage((string) $reservation->getId()));
            $io->text(\sprintf(
                '  Rappel envoyé pour "%s" — %s (arrivée %s)',
                $reservation->getProperty()?->getTitle(),
                $reservation->getGuest()?->getEmail(),
                $reservation->getCheckinDate()->format('d/m/Y'),
            ));
        }

        $io->success(\sprintf('%d rappel(s) check-in envoyé(s).', count($reservations)));

        return Command::SUCCESS;
    }
}
