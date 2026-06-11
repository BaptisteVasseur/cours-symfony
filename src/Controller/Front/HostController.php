<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\UnavailableDatesException;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Service\HostAvailabilityService;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote')]
#[IsGranted('ROLE_USER')]
final class HostController extends AbstractController
{
    #[Route('/demandes', name: 'app_host_requests', methods: ['GET'])]
    public function requests(ReservationRepository $reservationRepository): Response
    {
        return $this->render('front/host/requests.html.twig', [
            'reservations' => $reservationRepository->findPendingForHost($this->requireUser()),
        ]);
    }

    #[Route('/demandes/{id}/accepter', name: 'app_host_request_accept', methods: ['POST'])]
    public function accept(Reservation $reservation, Request $request, ReservationService $reservationService): Response
    {
        $this->assertHost($reservation->getProperty());
        $this->assertCsrf('moderate_' . $reservation->getId(), $request);

        try {
            $reservationService->confirm($reservation, $this->requireUser());
            $this->addFlash('success', 'Demande acceptée.');
        } catch (UnavailableDatesException) {
            $this->addFlash('error', 'Les dates ne sont plus disponibles, impossible de confirmer.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_requests');
    }

    #[Route('/demandes/{id}/refuser', name: 'app_host_request_refuse', methods: ['POST'])]
    public function refuse(Reservation $reservation, Request $request, ReservationService $reservationService): Response
    {
        $this->assertHost($reservation->getProperty());
        $this->assertCsrf('moderate_' . $reservation->getId(), $request);

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Le motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_requests');
        }

        try {
            $reservationService->refuse($reservation, $this->requireUser(), $reason);
            $this->addFlash('success', 'Demande refusée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_requests');
    }

    #[Route('/logements', name: 'app_host_properties', methods: ['GET'])]
    public function properties(PropertyRepository $propertyRepository): Response
    {
        return $this->render('front/host/properties.html.twig', [
            'properties' => $propertyRepository->findByHost($this->requireUser()),
        ]);
    }

    #[Route('/logements/{id}/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    public function calendar(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $this->assertHost($property);

        $monthParam = $request->query->get('month');
        $currentMonth = $monthParam
            ? \DateTimeImmutable::createFromFormat('Y-m', $monthParam) ?: new \DateTimeImmutable('first day of this month')
            : new \DateTimeImmutable('first day of this month');
        $currentMonth = $currentMonth->modify('first day of this month');

        $blockedDates = [];
        foreach ($availabilityRepository->findBlockedForProperty($property) as $avail) {
            $blockedDates[$avail->getAvailableDate()->format('Y-m-d')] = true;
        }

        $reservedRanges = [];
        foreach ($reservationRepository->findConfirmedForProperty($property) as $reservation) {
            $reservedRanges[] = [
                'from' => $reservation->getCheckinDate()->format('Y-m-d'),
                'to' => $reservation->getCheckoutDate()->format('Y-m-d'),
                'guest' => $reservation->getGuest()?->getEmail() ?? '',
            ];
        }

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'blocked' => $availabilityRepository->findBlockedForProperty($property),
            'blockedDates' => $blockedDates,
            'reservedRanges' => $reservedRanges,
            'currentMonth' => $currentMonth,
        ]);
    }

    #[Route('/logements/{id}/bloquer', name: 'app_host_block', methods: ['POST'])]
    public function block(Property $property, Request $request, HostAvailabilityService $hostAvailability): Response
    {
        $this->assertHost($property);
        $this->assertCsrf('block_' . $property->getId(), $request);

        $start = $this->parseDate($request->request->get('start'));
        $end = $this->parseDate($request->request->get('end'));

        if ($start === null || $end === null || $start >= $end) {
            $this->addFlash('error', 'Période invalide.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $blocked = $hostAvailability->blockPeriod($property, $start, $end);
        $this->addFlash('success', sprintf('%d jour(s) bloqué(s).', $blocked));

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/logements/{id}/calendar-token', name: 'app_host_calendar_token', methods: ['POST'])]
    public function calendarToken(Property $property, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertHost($property);
        $this->assertCsrf('token_' . $property->getId(), $request);

        $property->regenerateCalendarToken();
        $em->flush();
        $this->addFlash('success', 'Lien de calendrier régénéré.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertHost(?Property $property): void
    {
        if ($property === null || (string) $property->getHost()?->getId() !== (string) $this->requireUser()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
