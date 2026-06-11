<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\CheckinReminderMessage;
use App\Repository\BookingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:booking:send-reminders', description: 'Envoie un mail de rappel de check-in à J-1 aux voyageurs.')]
final class SendCheckInRemindersCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->setTime(0, 0, 0);
        $reservations = $this->bookingRepository->findConfirmedStartingOn($tomorrow);

        if ($reservations === []) {
            $io->success('Aucun rappel à envoyer.');

            return Command::SUCCESS;
        }

        $sentCount = 0;
        foreach ($reservations as $reservation) {
            $id = $reservation->getId();
            if ($id !== null) {
                $this->messageBus->dispatch(new CheckinReminderMessage($id->toRfc4122()));
                ++$sentCount;
            }
        }

        $io->success(sprintf('%d message(s) de rappel de check-in distribué(s).', $sentCount));

        return Command::SUCCESS;
    }
}
