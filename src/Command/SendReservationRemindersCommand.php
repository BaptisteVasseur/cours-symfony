<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\Notification\ReservationEmailSender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:send-reminders',
    description: 'Envoie les rappels e-mail J-1 pour les réservations confirmées.',
)]
final class SendReservationRemindersCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationEmailSender $emailSender,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $sent = 0;

        foreach ($this->reservationRepository->findConfirmedStartingOnWithoutReminder($tomorrow) as $reservation) {
            if (!$this->emailSender->sendReservationReminder($reservation)) {
                continue;
            }

            $reservation->setReminderSentAt(new \DateTimeImmutable());
            ++$sent;
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d rappel%s envoyé%s pour le %s.', $sent, $sent > 1 ? 's' : '', $sent > 1 ? 's' : '', $tomorrow->format('d/m/Y')));

        return Command::SUCCESS;
    }
}
