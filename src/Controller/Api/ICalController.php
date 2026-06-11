<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ICalController extends AbstractController
{
    #[Route('/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $token = $request->query->get('token');
        if ($token === null || $token !== $property->getIcalToken()) {
            throw new AccessDeniedException('Token iCal invalide.');
        }

        $bookings = $reservationRepository->findConfirmedByProperty($property);

        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Clone Airbnb//FR\r\n";
        foreach ($bookings as $booking) {
            $checkin = $booking->getCheckinDate();
            $checkout = $booking->getCheckoutDate();
            if ($checkin === null || $checkout === null) {
                continue;
            }

            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= 'UID:res-'.$booking->getId()."@clone-airbnb.local\r\n";
            $ical .= 'DTSTART;VALUE=DATE:'.$checkin->format('Ymd')."\r\n";
            $ical .= 'DTEND;VALUE=DATE:'.$checkout->format('Ymd')."\r\n";
            $ical .= 'SUMMARY:'.str_replace(["\r", "\n", ','], '', (string) $property->getTitle())."\r\n";
            $ical .= "END:VEVENT\r\n";
        }
        $ical .= "END:VCALENDAR\r\n";

        return new Response($ical, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }
}
