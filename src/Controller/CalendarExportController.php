<?php

namespace App\Controller;

use App\Entity\Logement;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendarExportController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_calendar_ics', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function export(Logement $logement, Request $request, ReservationRepository $reservations): Response
    {
        $token = (string) $request->query->get('token', '');
        if ($token === '' || !hash_equals($logement->icalToken, $token)) {
            return new Response('Token iCal invalide.', Response::HTTP_FORBIDDEN, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $contenu = $this->genererIcs($logement, $reservations->trouverConfirmeesPourLogement($logement));

        return new Response($contenu, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => sprintf('inline; filename="logement-%d.ics"', $logement->id),
        ]);
    }

    /**
     * @param list<Reservation> $reservations
     */
    private function genererIcs(Logement $logement, array $reservations): string
    {
        $lignes = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//StayShare//Reservation Calendar//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:StayShare - '.$this->echapperTexte($logement->titre),
        ];

        foreach ($reservations as $reservation) {
            $lignes = array_merge($lignes, $this->genererEvenement($reservation));
        }

        $lignes[] = 'END:VCALENDAR';

        return implode("\r\n", $lignes)."\r\n";
    }

    /**
     * @return list<string>
     */
    private function genererEvenement(Reservation $reservation): array
    {
        $voyageur = trim($reservation->voyageur->prenom.' '.$reservation->voyageur->nom);

        return [
            'BEGIN:VEVENT',
            'UID:res-'.$reservation->id.'@stayshare.local',
            'DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'SUMMARY:'.$this->echapperTexte($reservation->logement->titre.' - '.$voyageur),
            'DTSTART;VALUE=DATE:'.$reservation->dateArrivee->format('Ymd'),
            'DTEND;VALUE=DATE:'.$reservation->dateDepart->format('Ymd'),
            'DESCRIPTION:'.$this->echapperTexte(sprintf(
                'Sejour %d nuits - %s EUR - %s',
                $reservation->nombreNuits,
                $reservation->montantTotal,
                $reservation->voyageur->email,
            )),
            'END:VEVENT',
        ];
    }

    private function echapperTexte(string $texte): string
    {
        return str_replace(
            ["\\", ";", ",", "\r\n", "\n", "\r"],
            ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"],
            $texte,
        );
    }
}
