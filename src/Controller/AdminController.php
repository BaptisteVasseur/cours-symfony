<?php

namespace App\Controller;

use App\Dto\AdminStatsDto;
use App\Entity\Booking;
use App\Entity\Property;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Form\AdminUserType;
use App\Form\PropertyType;
use App\Repository\BookingRepository;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // ── Dashboard ──────────────────────────────────────────────────────────

    #[Route('', name: 'admin_dashboard')]
    public function dashboard(
        BookingRepository $bookingRepo,
        PropertyRepository $propertyRepo,
        UserRepository $userRepo,
    ): Response {
        $stats = new AdminStatsDto(
            totalBookings:     $bookingRepo->count([]),
            pendingBookings:   $bookingRepo->count(['status' => BookingStatus::PENDING]),
            confirmedBookings: $bookingRepo->count(['status' => BookingStatus::CONFIRMED]),
            totalRevenue:      $bookingRepo->getTotalRevenue(),
            totalUsers:        $userRepo->count([]),
            totalProperties:   $propertyRepo->count([]),
        );

        return $this->render('admin/dashboard.html.twig', [
            'stats'          => $stats,
            'recentBookings' => $bookingRepo->findRecentWithRelations(10),
        ]);
    }

    // ── Réservations ───────────────────────────────────────────────────────

    #[Route('/bookings', name: 'admin_bookings')]
    public function bookings(Request $request, BookingRepository $repo): Response
    {
        $status = $request->query->get('status');
        $bookings = $status
            ? $repo->findByStatusWithRelations(BookingStatus::from($status))
            : $repo->findAllWithRelations();

        return $this->render('admin/bookings.html.twig', [
            'bookings'      => $bookings,
            'currentStatus' => $status,
            'statusList'    => BookingStatus::cases(),
        ]);
    }

    #[Route('/bookings/{id}/status', name: 'admin_booking_status', methods: ['POST'])]
    public function updateBookingStatus(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $newStatus = BookingStatus::tryFrom($request->request->get('status', ''));

        if ($newStatus) {
            $booking->setStatus($newStatus);
            $em->flush();
            $this->addFlash('success', 'Statut mis à jour.');
        }

        return $this->redirectToRoute('admin_bookings', array_filter(['status' => $request->query->get('status')]));
    }

    #[Route('/bookings/{id}/delete', name: 'admin_booking_delete', methods: ['POST'])]
    public function deleteBooking(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_booking_' . $booking->getId(), $request->request->get('_token'))) {
            $em->remove($booking);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('admin_bookings');
    }

    // ── Utilisateurs ───────────────────────────────────────────────────────

    #[Route('/users', name: 'admin_users')]
    public function users(UserRepository $repo): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $repo->findAllWithStats(),
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        if ($this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('admin_users');
    }

    // ── Logements ──────────────────────────────────────────────────────────

    #[Route('/properties', name: 'admin_properties')]
    public function properties(PropertyRepository $repo): Response
    {
        return $this->render('admin/properties.html.twig', [
            'properties' => $repo->findAllWithRelations(),
        ]);
    }

    #[Route('/properties/{id}/edit', name: 'admin_property_edit', methods: ['GET', 'POST'])]
    public function editProperty(Property $property, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Logement mis à jour.');
            return $this->redirectToRoute('admin_properties');
        }

        return $this->render('admin/property_edit.html.twig', [
            'property' => $property,
            'form'     => $form,
        ]);
    }

    #[Route('/properties/{id}/delete', name: 'admin_property_delete', methods: ['POST'])]
    public function deleteProperty(Property $property, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_property_' . $property->getId(), $request->request->get('_token'))) {
            $em->remove($property);
            $em->flush();
            $this->addFlash('success', 'Logement supprimé.');
        }

        return $this->redirectToRoute('admin_properties');
    }
}
