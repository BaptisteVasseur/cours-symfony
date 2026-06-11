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

#[AsCommand(
    name: 'app:checkin:reminders',
    description: 'Sends check-in reminder emails to guests checking in tomorrow.',
)]
final class SendCheckinRemindersCommand extends Command
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

        $tomorrow = (new \DateTimeImmutable('tomorrow'))->setTime(0, 0, 0);
        $reservations = $this->reservationRepository->findCheckinOn($tomorrow);

        if (empty($reservations)) {
            $io->success('No check-ins tomorrow.');
            return Command::SUCCESS;
        }

        foreach ($reservations as $reservation) {
            $this->bus->dispatch(new CheckinReminderMessage((string) $reservation->getId()));
        }

        $io->success(sprintf('Dispatched %d check-in reminder(s).', \count($reservations)));

        return Command::SUCCESS;
    }
}
