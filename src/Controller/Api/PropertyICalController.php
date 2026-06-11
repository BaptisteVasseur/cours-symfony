<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Property;
use App\Repository\PropertyICalTokenRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/properties', name: 'api_property')]
final class PropertyICalController extends AbstractController
{
    /**
     * Export property reservations as iCal feed.
     * Secured with token parameter.
     *
     * URL format: /api/properties/{id}/calendar.ics?token={secret}
     */
    #[Route('/{id}/calendar.ics', name: 'api_property_ical_export', methods: ['GET'])]
    public function exportIcal(
        Property $property,
        Request $request,
        PropertyICalTokenRepository $tokenRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        // Get and validate token
        $tokenStr = $request->query->getString('token');

        if (empty($tokenStr)) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $token = $tokenRepository->findValidToken($property, $tokenStr);

        if ($token === null) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        // Update last accessed timestamp
        $token->updateLastAccessed();
        $this->getDoctrine()->getManager()->flush();

        // Get confirmed reservations for this property
        $reservations = $reservationRepository->findOverlappingReservations(
            $property,
            new \DateTimeImmutable('2020-01-01'),
            new \DateTimeImmutable('2099-12-31'),
            null
        );

        // Generate iCal feed
        $ical = $this->generateIcalFeed($property, $reservations);

        return new Response(
            $ical,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $property->getTitle() . '.ics"',
            ]
        );
    }

    /**
     * Generate iCal formatted content.
     *
     * @param list<Reservation> $reservations
     */
    private function generateIcalFeed(Property $property, array $reservations): string
    {
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Clone Airbnb//FR\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:" . $this->escapeIcalString($property->getTitle()) . "\r\n";
        $ical .= "X-WR-TIMEZONE:Europe/Paris\r\n";

        foreach ($reservations as $reservation) {
            if ($reservation->getStatus() !== 'confirmed') {
                continue;
            }

            $ical .= $this->buildIcalEvent($property, $reservation);
        }

        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    /**
     * Build a single iCal event.
     */
    private function buildIcalEvent(Property $property, object $reservation): string
    {
        $guest = $reservation->getGuest();
        $checkinDate = $reservation->getCheckinDate();
        $checkoutDate = $reservation->getCheckoutDate();
        $nights = $checkinDate->diff($checkoutDate)->days;

        $uid = sprintf(
            'res-%s@clone-airbnb.local',
            $reservation->getId()
        );

        $summary = sprintf(
            '%s — %s',
            $this->escapeIcalString($property->getTitle()),
            $this->escapeIcalString($guest->getUserIdentifier())
        );

        $description = sprintf(
            'Séjour %d nuits — %s€ — %s',
            $nights,
            $reservation->getTotalPrice(),
            $this->escapeIcalString($guest->getEmail() ?? '')
        );

        $event = "BEGIN:VEVENT\r\n";
        $event .= "UID:" . $uid . "\r\n";
        $event .= "DTSTAMP:" . (new \DateTimeImmutable())->format('Ymd\THis\Z') . "\r\n";
        $event .= "DTSTART;VALUE=DATE:" . $checkinDate->format('Ymd') . "\r\n";
        $event .= "DTEND;VALUE=DATE:" . $checkoutDate->format('Ymd') . "\r\n";
        $event .= "SUMMARY:" . $summary . "\r\n";
        $event .= "DESCRIPTION:" . $this->escapeIcalString($description) . "\r\n";
        $event .= "LOCATION:" . $this->escapeIcalString($property->getAddress()?->getCity() ?? '') . "\r\n";
        $event .= "STATUS:CONFIRMED\r\n";
        $event .= "END:VEVENT\r\n";

        return $event;
    }

    /**
     * Escape string for iCal format.
     */
    private function escapeIcalString(string $str): string
    {
        return addcslashes($str, "\n,;");
    }
}
