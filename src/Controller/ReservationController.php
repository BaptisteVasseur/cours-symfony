<?php

namespace App\Controller;

use App\Entity\Logement;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\DisponibiliteStatut;
use App\Enum\PaiementStatut;
use App\Enum\ReservationStatut;
use App\Repository\ReservationRepository;
use App\Service\DemandeReservationValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    #[Route('/mes-reservations', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservations): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations->trouverPourVoyageur($user),
        ]);
    }

    #[Route('/mes-reservations/{id}', name: 'app_reservation_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($reservation->voyageur->id !== $user->id && $reservation->hote->id !== $user->id) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }


    #[Route('/mes-reservations/{id}/paiement', name: 'app_reservation_payment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function payment(Reservation $reservation): Response
    {
        $this->verifierAccesVoyageur($reservation);

        if ($reservation->statut !== ReservationStatut::ACCEPTEE_EN_ATTENTE_PAIEMENT) {
            $this->addFlash('error', 'Cette reservation ne peut pas etre payee.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->id]);
        }

        return $this->render('reservation/payment.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/mes-reservations/{id}/paiement/confirmer', name: 'app_reservation_payment_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmPayment(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->verifierAccesVoyageur($reservation);

        if (!$this->isCsrfTokenValid('reservation_payment_'.$reservation->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Paiement expire. Reessayez.');

            return $this->redirectToRoute('app_reservation_payment', ['id' => $reservation->id]);
        }

        if ($reservation->statut !== ReservationStatut::ACCEPTEE_EN_ATTENTE_PAIEMENT) {
            $this->addFlash('error', 'Cette reservation ne peut pas etre payee.');

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->id]);
        }

        $paiement = new Paiement();
        $paiement->reservation = $reservation;
        $paiement->utilisateur = $reservation->voyageur;
        $paiement->prestataire = 'simulation';
        $paiement->referencePrestataire = 'SIM-'.strtoupper(bin2hex(random_bytes(4)));
        $paiement->montant = $reservation->montantTotal;
        $paiement->statut = PaiementStatut::PAYE;
        $paiement->datePaiement = new \DateTimeImmutable();
        $paiement->metadata = [
            'mode' => 'sandbox',
            'reservation_id' => $reservation->id,
        ];

        $reservation->statut = ReservationStatut::CONFIRMEE;
        $reservation->dateConfirmation = new \DateTimeImmutable();

        foreach ($reservation->logement->disponibilites as $disponibilite) {
            if ($disponibilite->date >= $reservation->dateArrivee && $disponibilite->date < $reservation->dateDepart) {
                $disponibilite->statut = DisponibiliteStatut::RESERVEE;
                $disponibilite->dateMiseAJour = new \DateTimeImmutable();
            }
        }

        $entityManager->persist($paiement);
        $entityManager->flush();

        $this->addFlash('success', 'Paiement confirme. Votre reservation est confirmee.');

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->id]);
    }

    #[Route('/logements/{id}/demande-reservation', name: 'app_reservation_request', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function requestReservation(
        Logement $logement,
        Request $request,
        DemandeReservationValidator $validator,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $user = $this->getUser();
        \assert($user instanceof User);

        if (!$this->isCsrfTokenValid('reservation_request_'.$logement->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La demande de reservation a expire. Reessayez.');

            return $this->redirectToRoute('app_logement_show', ['id' => $logement->id]);
        }

        $dateArrivee = $this->creerDate((string) $request->request->get('date_arrivee', ''));
        $dateDepart = $this->creerDate((string) $request->request->get('date_depart', ''));
        $voyageurs = (int) $request->request->get('voyageurs', 0);
        $message = trim((string) $request->request->get('message_voyageur', ''));

        if ($dateArrivee === null || $dateDepart === null || $voyageurs < 1) {
            $this->addFlash('error', 'Renseignez les dates et le nombre de voyageurs.');

            return $this->redirectToRoute('app_logement_show', ['id' => $logement->id]);
        }

        $motifsInvalides = $validator->getMotifsInvalides($user, $logement, $dateArrivee, $dateDepart, $voyageurs);
        if ($motifsInvalides !== []) {
            foreach ($motifsInvalides as $motif) {
                $this->addFlash('error', $motif);
            }

            return $this->redirectToRoute('app_logement_show', ['id' => $logement->id]);
        }

        $reservation = new Reservation();
        $reservation->logement = $logement;
        $reservation->voyageur = $user;
        $reservation->hote = $logement->hote;
        $reservation->dateArrivee = $dateArrivee;
        $reservation->dateDepart = $dateDepart;
        $reservation->nombreNuits = max(1, (int) $dateArrivee->diff($dateDepart)->days);
        $reservation->nombreVoyageurs = $voyageurs;
        $reservation->statut = ReservationStatut::EN_ATTENTE_HOTE;
        $reservation->messageVoyageur = $message !== '' ? $message : null;

        $this->calculerMontants($reservation);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de reservation envoyee a l hote.');

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->id]);
    }


    private function verifierAccesVoyageur(Reservation $reservation): void
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($reservation->voyageur->id !== $user->id) {
            throw $this->createAccessDeniedException();
        }
    }

    private function creerDate(string $valeur): ?\DateTimeImmutable
    {
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    private function calculerMontants(Reservation $reservation): void
    {
        $prixNuit = (float) ($reservation->logement->tarif?->prixNuit ?? '0.00');
        $fraisMenage = (float) ($reservation->logement->tarif?->fraisMenage ?? '0.00');
        $prixNuits = $prixNuit * $reservation->nombreNuits;
        $fraisService = round($prixNuits * 0.14, 2);
        $commissionPlateforme = round($prixNuits * 0.03, 2);
        $taxeSejour = 0.00;
        $montantTotal = $prixNuits + $fraisMenage + $fraisService + $taxeSejour;
        $montantHote = $prixNuits + $fraisMenage - $commissionPlateforme;

        $reservation->prixNuits = $this->decimal($prixNuits);
        $reservation->fraisMenage = $this->decimal($fraisMenage);
        $reservation->fraisService = $this->decimal($fraisService);
        $reservation->taxeSejour = $this->decimal($taxeSejour);
        $reservation->montantTotal = $this->decimal($montantTotal);
        $reservation->montantHote = $this->decimal($montantHote);
        $reservation->commissionPlateforme = $this->decimal($commissionPlateforme);
    }

    private function decimal(float $montant): string
    {
        return number_format($montant, 2, '.', '');
    }
}
