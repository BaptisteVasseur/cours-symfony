<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservation:complete',
    description: 'Passe en "completed" les réservations confirmées dont le checkout est dépassé.',
)]
final class CompleteReservationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationService $reservationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $reservations = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from('App\Entity\Reservation', 'r')
            ->leftJoin('r.property', 'p')
            ->leftJoin('p.host', 'host')
            ->andWhere('r.status = :status')
            ->andWhere('r.checkoutDate <= :today')
            ->setParameter('status', 'confirmed')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        if (empty($reservations)) {
            $io->info('Aucune réservation à clôturer.');
            return Command::SUCCESS;
        }

        // Acteur système : on utilise l'hôte du logement ou null
        foreach ($reservations as $reservation) {
            $actor = $reservation->getProperty()?->getHost();

            // Fallback si l'hôte n'est pas chargé
            if (!$actor instanceof User) {
                $reservation->setStatus('completed');
                $this->entityManager->flush();
                continue;
            }

            $this->reservationService->addStatusHistory($reservation, 'confirmed', 'completed', $actor);
            $reservation->setStatus('completed');
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d réservation(s) passée(s) en "completed".', count($reservations)));

        return Command::SUCCESS;
    }
}
