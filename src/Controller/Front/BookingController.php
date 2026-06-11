<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\Booking\BookingService;
use App\Service\Booking\BookingUnavailableException;
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

        if ($property->getHost()?->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre logement.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        $defaults = [];
        $checkinParam = $request->query->get('checkin');
        $checkoutParam = $request->query->get('checkout');
        $guestsParam = $request->query->get('guests');
        if (is_string($checkinParam) && $checkinParam !== '') {
            try {
                $defaults['checkinDate'] = new \DateTimeImmutable($checkinParam);
            } catch (\Exception) {
            }
        }
        if (is_string($checkoutParam) && $checkoutParam !== '') {
            try {
                $defaults['checkoutDate'] = new \DateTimeImmutable($checkoutParam);
            } catch (\Exception) {
            }
        }
        if (is_string($guestsParam) && ctype_digit($guestsParam) && (int) $guestsParam >= 1) {
            $defaults['guestsCount'] = (int) $guestsParam;
        }

        $form = $this->createForm(BookingType::class, $defaults);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $reservation = $bookingService->book(
                    $property,
                    $user,
                    $data['checkinDate'],
                    $data['checkoutDate'],
                    (int) $data['guestsCount'],
                );
            } catch (BookingUnavailableException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('app_booking_checkout', ['id' => $property->getId()]);
            }

            $this->addFlash(
                'success',
                $reservation->getStatus() === 'confirmed'
                    ? 'Votre réservation est confirmée.'
                    : 'Votre demande de réservation a été envoyée à l\'hôte.',
            );

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
