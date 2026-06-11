<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notifications transactionnelles (Partie D). L'envoi passe par Symfony Mailer,
 * routé vers Messenger (transport async) — voir config/packages/messenger.yaml.
 *
 * Le contexte des emails est volontairement composé de valeurs scalaires (pas
 * d'entités Doctrine) pour rester sérialisable dans la file et rendu par le worker
 * sans accès à l'EntityManager.
 */
final class ReservationMailer
{
    private const FROM = 'no-reply@airbnb-clone.local';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Nouvelle demande (Pending) -> Hôte : logement, dates, infos voyageur, total, CTA.
     */
    public function sendNewRequestToHost(Reservation $reservation): void
    {
        $host = $reservation->getProperty()?->getHost();
        if ($host?->getEmail() === null) {
            return;
        }

        $context = $this->context($reservation);
        $context['ctaUrl'] = $this->urlGenerator->generate('app_host_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->send($host->getEmail(), 'Nouvelle demande de réservation', 'emails/reservation_new_request.html.twig', $context);
    }

    /**
     * Réservation validée (Confirmed) -> Voyageur + Hôte : récapitulatif, coordonnées, montant.
     */
    public function sendConfirmation(Reservation $reservation): void
    {
        $context = $this->context($reservation);

        foreach ($this->partyEmails($reservation) as $recipient) {
            $this->send($recipient, 'Votre réservation est confirmée', 'emails/reservation_confirmed.html.twig', $context);
        }
    }

    /**
     * Refus ou Annulation -> partie(s) concernée(s) : motif explicite.
     */
    public function sendCancellation(Reservation $reservation): void
    {
        $context = $this->context($reservation);

        foreach ($this->partyEmails($reservation) as $recipient) {
            $this->send($recipient, 'Réservation annulée', 'emails/reservation_cancelled.html.twig', $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(string $to, string $subject, string $template, array $context): void
    {
        $email = (new TemplatedEmail())
            ->from(self::FROM)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->mailer->send($email);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Reservation $reservation): array
    {
        $property = $reservation->getProperty();
        $guest = $reservation->getGuest();
        $host = $property?->getHost();

        return [
            'propertyTitle' => $property?->getTitle(),
            'checkin' => $reservation->getCheckinDate(),
            'checkout' => $reservation->getCheckoutDate(),
            'guestsCount' => $reservation->getGuestsCount(),
            'total' => $reservation->getTotalPrice(),
            'currency' => $reservation->getCurrency() ?? 'EUR',
            'guestName' => $this->fullName($guest),
            'guestEmail' => $guest?->getEmail(),
            'hostName' => $this->fullName($host),
            'hostEmail' => $host?->getEmail(),
            'reason' => $reservation->getCancellationReason(),
        ];
    }

    /**
     * @return list<string>
     */
    private function partyEmails(Reservation $reservation): array
    {
        $emails = [];
        if ($reservation->getGuest()?->getEmail() !== null) {
            $emails[] = $reservation->getGuest()->getEmail();
        }
        if ($reservation->getProperty()?->getHost()?->getEmail() !== null) {
            $emails[] = $reservation->getProperty()->getHost()->getEmail();
        }

        return $emails;
    }

    private function fullName(?User $user): ?string
    {
        $profile = $user?->getProfile();
        if ($profile !== null && ($profile->getFirstName() !== null || $profile->getLastName() !== null)) {
            return trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? ''));
        }

        return $user?->getEmail();
    }
}
