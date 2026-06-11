<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\User;
use App\Message\BookingCancelledMessage;
use App\Service\NotificationService;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\ReservationWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
#[IsGranted('ROLE_USER')]
final class ReservationController extends AbstractController
{
    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/reservation/index.html.twig', [
            'reservations' => $reservationRepository->findByGuestForListing($user),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function show(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->findOneForDetail($reservation) ?? $reservation;

        return $this->render('front/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/calendar.ics', name: 'app_reservation_ical', methods: ['GET'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function ical(Reservation $reservation): Response
    {
        $now      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dtstamp  = $now->format('Ymd\THis\Z');
        $uid      = $reservation->getId().'@stayhub.local';
        $dtstart  = $reservation->getCheckinDate()?->format('Ymd') ?? '';
        $dtend    = $reservation->getCheckoutDate()?->format('Ymd') ?? '';
        $title    = $reservation->getProperty()?->getTitle() ?? 'Réservation';

        $address  = $reservation->getProperty()?->getAddress();
        $location = $address ? trim(($address->getCity() ?? '').' '.($address->getCountry() ?? '')) : '';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//StayHub//Reservation//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$dtstamp,
            'DTSTART;VALUE=DATE:'.$dtstart,
            'DTEND;VALUE=DATE:'.$dtend,
            'SUMMARY:Séjour — '.str_replace(["\r", "\n"], ' ', $title),
            'STATUS:CONFIRMED',
        ];
        if ($location !== '') {
            $lines[] = 'LOCATION:'.str_replace(["\r", "\n"], ' ', $location);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return new Response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reservation-'.substr((string) $reservation->getId(), 0, 8).'.ics"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function cancel(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        ReservationWorkflowService $workflowService,
        MessageBusInterface $bus,
        NotificationService $notificationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('cancel_'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        if (!in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
            $this->addFlash('error', 'Cette réservation ne peut pas être annulée.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $isGuest = $reservation->getGuest()?->getId() === $user->getId();
        $isHost  = $reservation->getProperty()?->getHost()?->getId() === $user->getId();

        if (!$isGuest && !$isHost) {
            throw $this->createAccessDeniedException();
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif d\'annulation est obligatoire.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $workflowService->transition($reservation, 'cancelled', $user, $reason);
        $notificationService->notifyBookingCancelled($reservation);
        $em->flush();

        $bus->dispatch(new BookingCancelledMessage((string) $reservation->getId()));
        $this->addFlash('success', 'Réservation annulée.');

        return $this->redirectToRoute('app_reservation_index');
    }
}
