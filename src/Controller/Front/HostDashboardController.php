<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Security\Roles;
use App\Security\Voter\ReservationVoter;
use App\Security\Voter\BookingVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/compte')]
final class HostDashboardController extends AbstractController
{
    #[Route('/devenir-hote', name: 'app_become_host', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function becomeHost(
        Request $request,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (in_array(Roles::HOST, $user->getRoles(), true)) {
            return $this->redirectToRoute('app_host_dashboard');
        }

        if ($request->isMethod('POST')) {
            $user->addAssignedRole(Roles::HOST);
            $entityManager->flush();

            $security->login($user);

            $this->addFlash('success', 'Félicitations ! Vous êtes maintenant un hôte. Bienvenue sur votre tableau de bord !');

            return $this->redirectToRoute('app_host_dashboard');
        }

        return $this->render('front/host_dashboard/devenir_hote.html.twig');
    }

    #[Route('/hote/dashboard', name: 'app_host_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_HOST')]
    public function dashboard(
        ReservationRepository $reservationRepository,
        PropertyRepository $propertyRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $totalEarnings = $reservationRepository->sumConfirmedRevenueByHost($user);
        $pendingCount = $reservationRepository->countPendingReservationsByHost($user);
        $activeListingsCount = $propertyRepository->count(['host' => $user, 'status' => 'published']);
        $totalListings = $propertyRepository->count(['host' => $user]);

        $occupancyRate = 0.0;
        if ($totalListings > 0) {
            $reservations = $reservationRepository->findByHostForListing($user);
            $occupiedDays = 0;
            foreach ($reservations as $res) {
                if (in_array($res->getStatus(), ['confirmed', 'completed'], true)) {
                    $interval = $res->getCheckinDate()->diff($res->getCheckoutDate());
                    $occupiedDays += max(1, $interval->days);
                }
            }
            $occupancyRate = round(($occupiedDays / ($totalListings * 30)) * 100, 1);
            if ($occupancyRate > 100.0) {
                $occupancyRate = 100.0;
            }
        }

        $recentReservations = array_slice($reservationRepository->findByHostForListing($user), 0, 5);

        return $this->render('front/host_dashboard/dashboard.html.twig', [
            'totalEarnings' => $totalEarnings,
            'pendingCount' => $pendingCount,
            'activeListingsCount' => $activeListingsCount,
            'occupancyRate' => $occupancyRate,
            'recentReservations' => $recentReservations,
        ]);
    }

    #[Route('/reservations/{id}/contact', name: 'app_reservation_contact', methods: ['GET', 'POST'])]
    #[IsGranted(BookingVoter::VIEW, subject: 'reservation')]
    public function contactGuest(
        Reservation $reservation,
        ConversationRepository $conversationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $conversation = $conversationRepository->findOneBy(['reservation' => $reservation]);

        if ($conversation === null) {
            $conversation = new Conversation();
            $conversation->setReservation($reservation);
            $entityManager->persist($conversation);

            $partGuest = new ConversationParticipant();
            $partGuest->setConversation($conversation);
            $partGuest->setUser($reservation->getGuest());
            $entityManager->persist($partGuest);

            $partHost = new ConversationParticipant();
            $partHost->setConversation($conversation);
            $partHost->setUser($reservation->getHost());
            $entityManager->persist($partHost);

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_messages_show', ['id' => $conversation->getId()]);
    }
}
