<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\InvalidReservationTransitionException;
use App\Repository\ReservationRepository;
use App\Service\Reservation\ReservationStateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTimeImmutable;

/**
 * Annule automatiquement les demandes « pending » non traitées au-delà d'un
 * délai (par défaut 24 h) — énoncé §G.1. La transition passe par le state
 * manager (historisée, sans auteur = système) et déclenche la notification.
 * Conçue pour le Cron.
 */
#[AsCommand(
    name: 'app:reservations:expire-pending',
    description: 'Annule les demandes de réservation en attente non traitées au-delà du délai.',
)]
final class ExpirePendingReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository   $reservationRepository,
        private readonly ReservationStateManager $stateManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Délai d\'expiration en heures', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = max(1, (int)$input->getOption('hours'));
        $threshold = (new DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        $expired = $this->reservationRepository->findPendingOlderThan($threshold);
        if ($expired === []) {
            $io->success('Aucune demande à expirer.');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $reservation) {
            try {
                $this->stateManager->cancel(
                    $reservation,
                    sprintf('Demande expirée : non traitée par l\'hôte sous %d h.', $hours),
                    null,
                );
                ++$count;
            } catch (InvalidReservationTransitionException $e) {
                $io->warning(sprintf('Réservation %s ignorée : %s', $reservation->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf('%d demande(s) expirée(s) et annulée(s).', $count));

        return Command::SUCCESS;
    }
}
