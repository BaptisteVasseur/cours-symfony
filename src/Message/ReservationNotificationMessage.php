<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReservationNotificationMessage
{
    public const TYPE_REQUEST_CREATED = 'request_created';
    public const TYPE_CONFIRMED = 'confirmed';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_CANCELLED = 'cancelled';

    public const INITIATOR_GUEST = 'guest';
    public const INITIATOR_HOST = 'host';
    public const INITIATOR_SYSTEM = 'system';

    public function __construct(
        private string $reservationId,
        private string $type,
        private ?string $initiator = null,
    ) {
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getInitiator(): ?string
    {
        return $this->initiator;
    }
}
