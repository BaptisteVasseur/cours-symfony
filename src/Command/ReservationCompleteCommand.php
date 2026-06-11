<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\ReservationLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:complete',
    description: 'Passe en "completed" les réservations confirmées dont la date de checkout est passée.',
)]
final class ReservationCompleteCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationLifecycleService $lifecycle,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $reservations = $this->reservationRepository->findConfirmedPastCheckout($today);

        if (count($reservations) === 0) {
            $io->success('Aucune réservation à compléter.');

            return Command::SUCCESS;
        }

        $systemUser = $this->userRepository->findOneBy(['email' => 'admin@airbnb-clone.fr']);
        if ($systemUser === null) {
            $io->error('Utilisateur système introuvable (admin@airbnb-clone.fr).');

            return Command::FAILURE;
        }

        $count = 0;
        foreach ($reservations as $reservation) {
            try {
                $this->lifecycle->complete($reservation, $systemUser);
                $count++;
            } catch (\LogicException $e) {
                $io->warning(sprintf('Réservation %s : %s', $reservation->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf('%d réservation(s) passée(s) en "completed".', $count));

        return Command::SUCCESS;
    }
}
