<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Exception\ReservationWorkflowException;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Service\BookingService;
use App\Security\Voter\PropertyVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BookingController extends AbstractController
{
    #[Route('/logement/{id}/reserver', name: 'app_booking_checkout', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::VIEW, subject: 'property')]
    public function checkout(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
        BookingService $bookingService,
    ): Response {
        if ($property->getStatus() !== 'published') {
            throw $this->createNotFoundException('Ce logement n\'est pas disponible à la réservation.');
        }

        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $defaultData = [
            'checkinDate' => $this->parseDate($request->query->get('checkin')),
            'checkoutDate' => $this->parseDate($request->query->get('checkout')),
            'guestsCount' => $request->query->getInt('guests') ?: null,
        ];

        $form = $this->createForm(BookingType::class, $defaultData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = $data['checkinDate'];
            $checkout = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            try {
                $reservation = $bookingService->createReservation(
                    $property,
                    $user,
                    $checkin,
                    $checkout,
                    $guestsCount,
                );

                $message = $reservation->getStatus() === 'confirmed'
                    ? 'Votre réservation est confirmée.'
                    : 'Votre demande a été envoyée à l\'hôte.';

                $this->addFlash('success', $message);

                return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
            } catch (ReservationWorkflowException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
