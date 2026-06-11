<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\User;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use App\Security\Voter\ReservationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservations')]
#[IsGranted('ROLE_USER')]
final class ReviewController extends AbstractController
{
    #[Route('/{id}/avis', name: 'app_reservation_review', methods: ['GET', 'POST'])]
    #[IsGranted(ReservationVoter::VIEW, subject: 'reservation')]
    public function new(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Seul le voyageur peut laisser un avis, et uniquement après la fin du séjour
        if ($reservation->getGuest()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'completed') {
            $this->addFlash('error', 'Vous ne pouvez laisser un avis qu\'après la fin de votre séjour.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        // Un seul avis par réservation
        $existing = $reviewRepository->findOneBy(['reservation' => $reservation, 'reviewer' => $user]);
        if ($existing !== null) {
            $this->addFlash('info', 'Vous avez déjà laissé un avis pour cette réservation.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        $review = new Review();
        $form   = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setReservation($reservation);
            $review->setReviewer($user);
            $review->setReviewedUser($reservation->getProperty()?->getHost());
            $review->setProperty($reservation->getProperty());

            $em->persist($review);
            $em->flush();

            $this->addFlash('success', 'Merci pour votre avis !');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('front/reservation/review.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }
}
