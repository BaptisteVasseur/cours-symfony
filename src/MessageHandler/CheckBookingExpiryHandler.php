<?php

namespace App\MessageHandler;

use App\Message\CheckBookingExpiryMessage;
use App\Repository\BookingRepository;
use App\Service\BookingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckBookingExpiryHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepo,
        private readonly BookingService    $bookingService,
    ) {}

    public function __invoke(CheckBookingExpiryMessage $message): void
    {
        $booking = $this->bookingRepo->find($message->bookingId);

        if ($booking === null || $booking->getStatus() !== 'pending') {
            return;
        }

        $this->bookingService->cancel($booking, 'Demande non traitée dans les 24h.', 'system');
    }
}
