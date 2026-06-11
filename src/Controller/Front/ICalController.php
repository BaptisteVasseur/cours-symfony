<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_property_ical', methods: ['GET'])]
    public function export(Property $property, ReservationRepository $reservationRepository): Response
    {
        if ($property->getStatus() !== 'published') {
            throw $this->createNotFoundException();
        }

        $reservations = $reservationRepository->findConfirmedForProperty($property);

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Clone Airbnb//FR';
        $lines[] = 'CALSCALE:GREGORIAN';

        foreach ($reservations as $reservation) {
            $guest = $reservation->getGuest();
            $nights = $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = 'SUMMARY:' . $property->getTitle() . ' — ' . $guest->getEmail();
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = 'DESCRIPTION:Séjour ' . $nights . ' nuits — ' . $reservation->getTotalPrice() . '€ — ' . $guest->getEmail();
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }
}