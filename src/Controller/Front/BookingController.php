<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\ReservationService;
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
        ReservationService $reservationService,
    ): Response {
        if ($property->getStatus() !== 'published') {
            throw $this->createNotFoundException('Ce logement n\'est pas disponible à la réservation.');
        }

        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost()?->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre logement.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        $form = $this->createForm(BookingType::class, null, [
            'checkin' => $request->query->get('checkin'),
            'checkout' => $request->query->get('checkout'),
            'guests' => $request->query->getInt('guests') ?: null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $checkin = $data['checkinDate'];
            $checkout = $data['checkoutDate'];
            $guestsCount = (int) $data['guestsCount'];

            if ($checkin >= $checkout) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            try {
                $reservation = $reservationService->createBooking($property, $user, $checkin, $checkout, $guestsCount);
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $status = $reservation->getStatus();
            if ($status === 'confirmed') {
                $this->addFlash('success', 'Réservation confirmée instantanément ! Bon séjour.');
            } else {
                $this->addFlash('info', 'Votre demande a bien été envoyée. L\'hôte va examiner votre demande.');
            }

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
