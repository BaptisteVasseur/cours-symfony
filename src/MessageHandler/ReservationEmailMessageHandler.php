<?php

namespace App\MessageHandler;

use App\Entity\Reservation;
use App\Message\ReservationEmailMessage;
use App\Repository\ReservationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class ReservationEmailMessageHandler
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ReservationEmailMessage $message): void
    {
        $reservation = $this->reservations->find($message->reservationId);
        if (!$reservation instanceof Reservation) {
            return;
        }

        foreach ($this->creerEmails($reservation, $message->type) as $email) {
            $this->mailer->send($email);
        }
    }

    /**
     * @return list<Email>
     */
    private function creerEmails(Reservation $reservation, string $type): array
    {
        return match ($type) {
            ReservationEmailMessage::NOUVELLE_DEMANDE => [
                $this->emailHote(
                    $reservation,
                    'Nouvelle demande de reservation',
                    sprintf('%s souhaite reserver votre logement %s du %s au %s.', $reservation->voyageur->prenom, $reservation->logement->titre, $this->date($reservation->dateArrivee), $this->date($reservation->dateDepart)),
                ),
            ],
            ReservationEmailMessage::RESERVATION_INSTANTANEE => [
                $this->emailHote(
                    $reservation,
                    'Reservation instantanee confirmee',
                    sprintf('%s a reserve automatiquement %s du %s au %s.', $reservation->voyageur->prenom, $reservation->logement->titre, $this->date($reservation->dateArrivee), $this->date($reservation->dateDepart)),
                ),
                $this->emailVoyageur(
                    $reservation,
                    'Votre reservation est confirmee',
                    sprintf('Votre reservation pour %s est confirmee du %s au %s.', $reservation->logement->titre, $this->date($reservation->dateArrivee), $this->date($reservation->dateDepart)),
                ),
            ],
            ReservationEmailMessage::RESERVATION_ACCEPTEE => [
                $this->emailVoyageur(
                    $reservation,
                    'Votre demande a ete acceptee',
                    sprintf('Votre demande pour %s a ete acceptee. Vous pouvez maintenant proceder au paiement.', $reservation->logement->titre),
                ),
            ],
            ReservationEmailMessage::RESERVATION_REFUSEE => [
                $this->emailVoyageur(
                    $reservation,
                    'Votre demande a ete refusee',
                    sprintf('Votre demande pour %s a ete refusee. Motif : %s', $reservation->logement->titre, $reservation->motifRefus ?? 'Non renseigne'),
                ),
            ],
            ReservationEmailMessage::RESERVATION_PAYEE => [
                $this->emailHote(
                    $reservation,
                    'Reservation payee et confirmee',
                    sprintf('%s a paye sa reservation pour %s. Montant total : %s EUR.', $reservation->voyageur->prenom, $reservation->logement->titre, $reservation->montantTotal),
                ),
                $this->emailVoyageur(
                    $reservation,
                    'Paiement confirme',
                    sprintf('Votre paiement pour %s est confirme. Bon sejour !', $reservation->logement->titre),
                ),
            ],
            ReservationEmailMessage::ANNULATION_VOYAGEUR => [
                $this->emailHote(
                    $reservation,
                    'Reservation annulee par le voyageur',
                    sprintf('%s a annule sa reservation pour %s. Motif : %s', $reservation->voyageur->prenom, $reservation->logement->titre, $reservation->motifAnnulation ?? 'Non renseigne'),
                ),
            ],
            ReservationEmailMessage::ANNULATION_HOTE => [
                $this->emailVoyageur(
                    $reservation,
                    'Reservation annulee par l hote',
                    sprintf('Votre reservation pour %s a ete annulee par l hote. Motif : %s', $reservation->logement->titre, $reservation->motifAnnulation ?? 'Non renseigne'),
                ),
            ],
            default => [],
        };
    }

    private function emailHote(Reservation $reservation, string $sujet, string $message): Email
    {
        return $this->creerEmail($reservation->hote->email, $sujet, $message, $reservation);
    }

    private function emailVoyageur(Reservation $reservation, string $sujet, string $message): Email
    {
        return $this->creerEmail($reservation->voyageur->email, $sujet, $message, $reservation);
    }

    private function creerEmail(string $destinataire, string $sujet, string $message, Reservation $reservation): Email
    {
        $html = sprintf(
            '<h1>%s</h1><p>%s</p><hr><p><strong>Logement :</strong> %s<br><strong>Dates :</strong> %s - %s<br><strong>Voyageur :</strong> %s %s<br><strong>Total :</strong> %s EUR</p>',
            htmlspecialchars($sujet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($reservation->logement->titre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $this->date($reservation->dateArrivee),
            $this->date($reservation->dateDepart),
            htmlspecialchars($reservation->voyageur->prenom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($reservation->voyageur->nom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($reservation->montantTotal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );

        return (new Email())
            ->from('no-reply@stayshare.local')
            ->to($destinataire)
            ->subject('[StayShare] '.$sujet)
            ->html($html)
            ->text(strip_tags(str_replace(['<br>', '<br />', '<hr>'], "\n", $html)));
    }

    private function date(\DateTimeInterface $date): string
    {
        return $date->format('d/m/Y');
    }
}
