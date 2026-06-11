<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BookingRepository;
use App\Service\BookingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:booking:complete', description: 'Marque les reservations terminees comme completees.')]
final class CompleteBookingsCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingService $bookingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');
        $reservations = $this->bookingRepository->findConfirmedPastCheckout($today);

        if ($reservations === []) {
            $io->success('Aucune reservation a completer.');

            return Command::SUCCESS;
        }

        $completedCount = 0;
        $hasFailure = false;

        foreach ($reservations as $reservation) {
            try {
                $this->bookingService->markCompleted($reservation);
                ++$completedCount;
            } catch (\Throwable $exception) {
                $hasFailure = true;
                $io->error(sprintf(
                    'Reservation %s : %s',
                    $reservation->getId()?->toRfc4122() ?? 'inconnue',
                    $exception->getMessage(),
                ));
            }
        }

        $io->success(sprintf('%d reservation(s) marquee(s) comme completee(s).', $completedCount));

        return $hasFailure ? Command::FAILURE : Command::SUCCESS;
    }
}
