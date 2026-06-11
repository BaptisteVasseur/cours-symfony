<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReservationConfirmedMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class ReservationConfirmedMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly string $appName = 'Airbnb',
        private readonly string $fromEmail = 'noreply@airbnb.local',
    ) {}

    public function __invoke(ReservationConfirmedMessage $message): void
    {
        $reservation = $this->reservationRepository->findOneForDetail(
            $this->reservationRepository->find($message->reservationId)
                ?? throw new \InvalidArgumentException('Reservation not found: ' . $message->reservationId),
        );

        if ($reservation === null) {
            return;
        }

        $guest = $reservation->getGuest();
        $property = $reservation->getProperty();

        if ($guest === null || $property === null) {
            return;
        }

        $guestEmail = $guest->getEmail();
        $guestName = $guest->getProfile()?->getFirstName() ?? $guestEmail;
        $propertyTitle = $property->getTitle() ?? 'votre logement';
        $checkin = $reservation->getCheckinDate()?->format('d/m/Y') ?? '';
        $checkout = $reservation->getCheckoutDate()?->format('d/m/Y') ?? '';
        $status = $reservation->getStatus();
        $isConfirmed = $status === 'confirmed';

        // Email to guest
        if ($guestEmail !== null) {
            $subject = $isConfirmed
                ? "✅ Réservation confirmée – {$propertyTitle}"
                : "⏳ Réservation en attente de confirmation – {$propertyTitle}";

            $guestBody = $this->buildGuestEmailHtml(
                guestName: $guestName,
                propertyTitle: $propertyTitle,
                checkin: $checkin,
                checkout: $checkout,
                guestsCount: $reservation->getGuestsCount() ?? 1,
                totalPrice: $reservation->getTotalPrice() ?? '0',
                currency: $reservation->getCurrency() ?? 'EUR',
                isConfirmed: $isConfirmed,
            );

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->appName))
                ->to(new Address($guestEmail, $guestName))
                ->subject($subject)
                ->html($guestBody);

            $this->mailer->send($email);
        }

        // Email to host
        $host = $property->getHost();
        $hostEmail = $host?->getEmail();
        if ($hostEmail !== null) {
            $hostName = $host->getProfile()?->getFirstName() ?? $hostEmail;
            $hostSubject = "📅 Nouvelle demande de réservation – {$propertyTitle}";

            $hostBody = $this->buildHostEmailHtml(
                hostName: $hostName,
                guestName: $guestName,
                propertyTitle: $propertyTitle,
                checkin: $checkin,
                checkout: $checkout,
                guestsCount: $reservation->getGuestsCount() ?? 1,
                totalPrice: $reservation->getTotalPrice() ?? '0',
                currency: $reservation->getCurrency() ?? 'EUR',
                isConfirmed: $isConfirmed,
            );

            $hostEmail = (new Email())
                ->from(new Address($this->fromEmail, $this->appName))
                ->to(new Address($host->getEmail(), $hostName))
                ->subject($hostSubject)
                ->html($hostBody);

            $this->mailer->send($hostEmail);
        }
    }

    private function buildGuestEmailHtml(
        string $guestName,
        string $propertyTitle,
        string $checkin,
        string $checkout,
        int $guestsCount,
        string $totalPrice,
        string $currency,
        bool $isConfirmed,
    ): string {
        $statusBadge = $isConfirmed
            ? '<span style="background:#dcfce7;color:#166534;padding:4px 12px;border-radius:20px;font-size:14px;">✅ Confirmée</span>'
            : '<span style="background:#fef9c3;color:#854d0e;padding:4px 12px;border-radius:20px;font-size:14px;">⏳ En attente</span>';

        $statusMessage = $isConfirmed
            ? 'Votre réservation est confirmée ! Préparez vos valises 🎉'
            : 'Votre demande a bien été reçue. L\'hôte doit la confirmer sous 24h.';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <body style="font-family:sans-serif;background:#f9fafb;margin:0;padding:0;">
        <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
            <div style="background:#FF385C;padding:32px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:28px;">airbnb</h1>
            </div>
            <div style="padding:32px;">
                <p style="font-size:18px;font-weight:600;color:#111;">Bonjour {$guestName},</p>
                <p style="color:#555;">{$statusMessage}</p>
                <div style="background:#f9fafb;border-radius:12px;padding:20px;margin:24px 0;">
                    <div style="margin-bottom:8px;">{$statusBadge}</div>
                    <h2 style="margin:16px 0 8px;font-size:18px;color:#111;">{$propertyTitle}</h2>
                    <table style="width:100%;border-collapse:collapse;font-size:14px;color:#555;">
                        <tr><td style="padding:6px 0;">📅 Arrivée</td><td style="text-align:right;font-weight:600;color:#111;">{$checkin}</td></tr>
                        <tr><td style="padding:6px 0;">📅 Départ</td><td style="text-align:right;font-weight:600;color:#111;">{$checkout}</td></tr>
                        <tr><td style="padding:6px 0;">👥 Voyageurs</td><td style="text-align:right;font-weight:600;color:#111;">{$guestsCount}</td></tr>
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td style="padding:12px 0 6px;font-weight:700;color:#111;">Total</td>
                            <td style="text-align:right;font-weight:700;font-size:18px;color:#FF385C;">{$totalPrice} {$currency}</td>
                        </tr>
                    </table>
                </div>
                <p style="color:#888;font-size:13px;">Merci de faire confiance à {$this->appName}.</p>
            </div>
        </div>
        </body>
        </html>
        HTML;
    }

    private function buildHostEmailHtml(
        string $hostName,
        string $guestName,
        string $propertyTitle,
        string $checkin,
        string $checkout,
        int $guestsCount,
        string $totalPrice,
        string $currency,
        bool $isConfirmed,
    ): string {
        $statusText = $isConfirmed ? 'confirmée automatiquement (réservation instantanée)' : 'en attente de votre confirmation';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <body style="font-family:sans-serif;background:#f9fafb;margin:0;padding:0;">
        <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
            <div style="background:#FF385C;padding:32px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:28px;">airbnb</h1>
            </div>
            <div style="padding:32px;">
                <p style="font-size:18px;font-weight:600;color:#111;">Bonjour {$hostName},</p>
                <p style="color:#555;">Une nouvelle demande de réservation pour <strong>{$propertyTitle}</strong> est {$statusText}.</p>
                <div style="background:#f9fafb;border-radius:12px;padding:20px;margin:24px 0;font-size:14px;color:#555;">
                    <p><strong>Voyageur :</strong> {$guestName}</p>
                    <p><strong>Arrivée :</strong> {$checkin} → <strong>Départ :</strong> {$checkout}</p>
                    <p><strong>Voyageurs :</strong> {$guestsCount} · <strong>Total :</strong> <span style="color:#FF385C;font-weight:700;">{$totalPrice} {$currency}</span></p>
                </div>
                <p style="color:#888;font-size:13px;">Connectez-vous à votre espace hôte pour gérer cette réservation.</p>
            </div>
        </div>
        </body>
        </html>
        HTML;
    }
}
