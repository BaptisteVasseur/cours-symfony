<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\ReservationWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Worker d'expiration des demandes "pending" non traitées (G.1). À lancer en
 * tâche planifiée (cron, ex. toutes les 5 minutes).
 *
 * Deux délais selon le mode de réservation :
 *  - réservation instantanée : verrou de paiement de 15 min dépassé → le
 *    voyageur n'a pas payé, on libère le créneau ;
 *  - sur demande : pas de traitement par l'hôte après 24 h → on annule.
 *
 * Chaque annulation trace l'historique (acteur = système) et notifie les
 * parties (in-app + e-mail asynchrone), comme une annulation classique.
 */
#[AsCommand(
    name: 'app:reservations:expire',
    description: 'Annule automatiquement les demandes pending expirées (paiement 15 min, modération 24 h)',
)]
final class ExpirePendingReservationsCommand extends Command
{
    private const ON_REQUEST_DEADLINE_HOURS = 24;

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationWorkflow $workflow,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $onRequestThreshold = $now->modify(sprintf('-%d hours', self::ON_REQUEST_DEADLINE_HOURS));

        // Toutes les demandes encore en attente : chacune est filtrée ensuite
        // selon son propre délai (15 min instantané / 24 h sur demande).
        $pending = $this->reservationRepository->findPendingCreatedBefore($now);

        $expired = 0;
        foreach ($pending as $reservation) {
            $isInstant = $reservation->getProperty()?->isInstantBooking() === true;

            if ($isInstant) {
                if ($this->workflow->expireStalePaymentLock($reservation)) {
                    ++$expired;
                    $io->writeln(sprintf('  • %s : paiement non effectué (15 min) → annulée', $reservation->getId()));
                }
                continue;
            }

            if ($reservation->getCreatedAt() !== null && $reservation->getCreatedAt() < $onRequestThreshold) {
                $reason = sprintf('Annulation automatique : demande non traitée par l\'hôte sous %d h.', self::ON_REQUEST_DEADLINE_HOURS);
                if ($this->workflow->expirePending($reservation, $reason)) {
                    ++$expired;
                    $io->writeln(sprintf('  • %s : non traitée (24 h) → annulée', $reservation->getId()));
                }
            }
        }

        $io->success(sprintf('%d demande(s) expirée(s) sur %d en attente.', $expired, count($pending)));

        return Command::SUCCESS;
    }
}
