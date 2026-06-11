<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Message\ReservationEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class ReservationEmailNotifier
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function nouvelleDemande(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationEmailMessage::NOUVELLE_DEMANDE);
    }

    public function reservationInstantanee(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationEmailMessage::RESERVATION_INSTANTANEE);
    }

    public function reservationAcceptee(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationEmailMessage::RESERVATION_ACCEPTEE);
    }

    public function reservationRefusee(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationEmailMessage::RESERVATION_REFUSEE);
    }

    public function reservationPayee(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationEmailMessage::RESERVATION_PAYEE);
    }

    public function annulationVoyageur(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationEmailMessage::ANNULATION_VOYAGEUR);
    }

    public function annulationHote(Reservation $reservation): void
    {
        $this->dispatch($reservation, ReservationEmailMessage::ANNULATION_HOTE);
    }

    private function dispatch(Reservation $reservation, string $type): void
    {
        if ($reservation->id === null) {
            return;
        }

        $this->bus->dispatch(new ReservationEmailMessage($reservation->id, $type));
    }
}
