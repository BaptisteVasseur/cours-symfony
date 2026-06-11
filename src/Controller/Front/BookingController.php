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

        /** @var User $user */
        $user = $this->getUser();

        if ($property->getHost()?->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre logement.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }
       

        // Pré-remplissage depuis les query params (outil de recherche ou fiche logement).
        $defaults = $this->parseDateDefaults($request);

        $form = $this->createForm(BookingType::class, $defaults);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $reservation = $reservationService->create($property, $user, $data);
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('front/property/booking.html.twig', [
                    'property' => $property,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $flashMessage = $reservation->getStatus() === 'confirmed'
                ? 'Votre réservation a été confirmée ! Vous recevrez un email de confirmation.'
                : 'Votre demande de réservation a été envoyée. L\'hôte vous répondra prochainement.';

            $this->addFlash('success', $flashMessage);

            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }
     
        return $this->render('front/property/booking.html.twig', [
            'property' => $property,
            'form' => $form,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    /**
     * Construit un tableau de valeurs par défaut pour le formulaire
     * à partir des query params ?checkin=YYYY-MM-DD&checkout=YYYY-MM-DD&guests=N.
     *
     * @return array{checkinDate: ?\DateTimeImmutable, checkoutDate: ?\DateTimeImmutable, guestsCount: int}
     */
    private function parseDateDefaults(Request $request): array
    {
        $parse = static function (?string $value): ?\DateTimeImmutable {
            if ($value === null || $value === '') {
                return null;
            }
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

            return $date !== false ? $date : null;
        };

        return [
            'checkinDate' => $parse($request->query->get('checkin')),
            'checkoutDate' => $parse($request->query->get('checkout')),
            'guestsCount' => max(1, $request->query->getInt('guests', 1)),
        ];
    }
}

