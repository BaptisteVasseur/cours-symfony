<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Reservation;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bookings:expire',
    description: 'Annule automatiquement les demandes de réservation en attente depuis plus de 24h.',
)]
class ExpireBookingsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingService $bookingService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = new \DateTimeImmutable('-24 hours');

        $expiredReservations = $this->entityManager->getRepository(Reservation::class)->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.createdAt < :limit')
            ->setParameter('status', 'pending')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();

        $count = count($expiredReservations);
        if ($count === 0) {
            $io->info('Aucune demande de réservation à expirer.');
            return Command::SUCCESS;
        }

        foreach ($expiredReservations as $reservation) {
            $this->bookingService->cancel($reservation, 'Annulation automatique après 24h sans réponse de l\'hôte.');
        }

        $io->success(sprintf('%d réservation(s) ont été annulées automatiquement.', $count));

        return Command::SUCCESS;
    }
}
