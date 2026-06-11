<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Contrat commun des messages de notification liés à une réservation. Le message
 * ne transporte que l'identifiant (scalaire) : le handler recharge l'entité,
 * évitant de sérialiser un objet Doctrine dans la file. Permet aussi de router
 * tous ces messages vers le transport « async » via cette seule interface.
 */
interface ReservationNotificationInterface
{
    public function getReservationId(): string;
}
