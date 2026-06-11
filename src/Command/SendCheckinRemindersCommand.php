<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\BookingCheckinReminderMessage;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:bookings:checkin-reminder',
    description: 'Envoie aux voyageurs le rappel de check-in pour les arrivées du lendemain.',
)]
final class SendCheckinRemindersCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Nombre de jours avant l\'arrivée', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(0, (int) $input->getOption('days'));
        $targetDay = (new \DateTimeImmutable())->setTime(0, 0)->modify("+{$days} days");

        $bookings = $this->bookingRepository->findNeedingCheckinReminder($targetDay);

        if ($bookings === []) {
            $io->success(sprintf('Aucun rappel à envoyer pour le %s.', $targetDay->format('d/m/Y')));

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        foreach ($bookings as $booking) {
            // On marque AVANT de dispatcher (idempotence) : un rejeu de la
            // commande ne renverra pas le même rappel.
            $booking->setCheckinReminderSentAt($now);
        }
        $this->em->flush();

        foreach ($bookings as $booking) {
            $this->bus->dispatch(new BookingCheckinReminderMessage((string) $booking->getId()));
            $io->writeln(sprintf(
                ' - Rappel programmé pour %s (%s).',
                $booking->getGuest()?->getEmail(),
                $booking->getListing()?->getTitle(),
            ));
        }

        $io->success(sprintf(
            '%d rappel(s) de check-in envoyé(s) pour le %s.',
            count($bookings),
            $targetDay->format('d/m/Y')
        ));

        return Command::SUCCESS;
    }
}
