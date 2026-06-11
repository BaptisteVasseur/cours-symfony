<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ICalController extends AbstractController
{
    #[Route('/api/properties/{id}/calendar.ics', name: 'app_ical_export', methods: ['GET'])]
    public function export(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepository,
    ): Response {
        $token         = $request->query->get('token');
        $storedToken   = $property->getCalendarToken();

        if (!$token || $storedToken === null || !hash_equals($storedToken, $token)) {
            throw $this->createAccessDeniedException('Token iCal invalide.');
        }

        $reservations = $reservationRepository->findConfirmedByProperty($property);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clone Airbnb//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($property->getTitle()),
        ];

        foreach ($reservations as $reservation) {
            $guest        = $reservation->getGuest();
            $guestProfile = $guest?->getProfile();
            $guestName    = $guestProfile
                ? trim($guestProfile->getFirstName() . ' ' . $guestProfile->getLastName())
                : $guest?->getEmail() ?? 'Voyageur';

            $nights = $reservation->getCheckinDate()->diff($reservation->getCheckoutDate())->days;

            $description = sprintf(
                'Séjour %d nuit%s — %s€ — %s',
                $nights,
                $nights > 1 ? 's' : '',
                $reservation->getTotalPrice(),
                $guest?->getEmail() ?? '',
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:res-' . $reservation->getId() . '@clone-airbnb.local';
            $lines[] = $this->foldLine('SUMMARY:' . $this->escapeText($property->getTitle() . ' — ' . $guestName));
            $lines[] = 'DTSTART;VALUE=DATE:' . $reservation->getCheckinDate()->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $reservation->getCheckoutDate()->format('Ymd');
            $lines[] = $this->foldLine('DESCRIPTION:' . $this->escapeText($description));
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return new Response($content, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
            'Cache-Control'       => 'no-cache, no-store',
        ]);
    }

    #[Route('/compte/hote/logements/{id}/ical/generer', name: 'app_host_ical_generate', methods: ['POST'])]
    public function generateToken(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('ical_generate'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
        }

        $property->setCalendarToken(bin2hex(random_bytes(32)));
        $entityManager->flush();

        $this->addFlash('success', 'Lien iCal généré.');

        return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
    }

    #[Route('/compte/hote/logements/{id}/ical/revoquer', name: 'app_host_ical_revoke', methods: ['POST'])]
    public function revokeToken(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('ical_revoke'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
        }

        $property->setCalendarToken(null);
        $entityManager->flush();

        $this->addFlash('success', 'Lien iCal révoqué.');

        return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
    }

    private function escapeText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace("\n", '\\n', $text);

        return $text;
    }

    private function foldLine(string $line): string
    {
        if (mb_strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        while (mb_strlen($line) > 75) {
            $folded .= mb_substr($line, 0, 75) . "\r\n ";
            $line = mb_substr($line, 75);
        }

        return $folded . $line;
    }
}
