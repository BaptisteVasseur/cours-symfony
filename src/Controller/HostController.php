<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
final class HostController extends AbstractController
{
    #[Route('', name: 'app_host_dashboard', methods: ['GET'])]
    public function dashboard(PropertyRepository $propertyRepository, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('host/dashboard.html.twig', [
            'properties' => $propertyRepository->findForHostDashboard($user),
            'totalProperties' => $propertyRepository->countForHost($user),
            'totalReservations' => $reservationRepository->countForHostByStatus($user),
            'pendingReservations' => $reservationRepository->countForHostByStatus($user, 'pending'),
            'confirmedReservations' => $reservationRepository->countForHostByStatus($user, 'confirmed'),
            'revenue' => $reservationRepository->sumRevenueForHost($user),
        ]);
    }

    #[Route('/property/{id}/availability', name: 'app_host_availability', methods: ['GET', 'POST'])]
    public function availability(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
        PropertyRepository $propertyRepository,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas le propriétaire de ce logement.');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('availability_' . ($property->getId()?->toRfc4122() ?? ''), $submittedToken)) {
                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
            }

            $checkinDate = $request->request->get('checkinDate');
            $checkoutDate = $request->request->get('checkoutDate');

            try {
                $checkin = new \DateTimeImmutable($checkinDate);
                $checkout = new \DateTimeImmutable($checkoutDate);
            } catch (\Exception) {
                $this->addFlash('error', 'Dates invalides.');

                return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
            }

            if ($checkout <= $checkin) {
                $this->addFlash('error', 'La date de départ doit être postérieure à la date d\'arrivée.');

                return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
            }

            $period = new \DatePeriod($checkin, new \DateInterval('P1D'), $checkout);
            $created = 0;

            foreach ($period as $date) {
                $dateImmutable = $date;

                $existing = $availabilityRepository->findOneBy([
                    'property' => $property,
                    'availableDate' => $dateImmutable,
                ]);

                if ($existing !== null) {
                    continue;
                }

                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($dateImmutable);
                $availability->setIsAvailable(false);

                $entityManager->persist($availability);
                ++$created;
            }

            if ($created > 0) {
                $entityManager->flush();
                $this->addFlash('success', sprintf('%d jour(s) bloqué(s) avec succès.', $created));
            } else {
                $this->addFlash('success', 'Ces dates sont déjà bloquées.');
            }

            return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
        }

        $blockedDates = $availabilityRepository->findBlockedForProperty((string) $property->getId());

        return $this->render('host/availability.html.twig', [
            'property' => $property,
            'blockedDates' => $blockedDates,
        ]);
    }

    #[Route('/property/availability/{id}/delete', name: 'app_host_availability_delete', methods: ['POST'])]
    public function deleteAvailability(
        Request $request,
        PropertyAvailability $availability,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        $property = $availability->getProperty();

        if (!$user instanceof User || $property === null || $property->getHost() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas le propriétaire de ce logement.');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_' . ($availability->getId()?->toRfc4122() ?? ''), $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
        }

        $entityManager->remove($availability);
        $entityManager->flush();

        $this->addFlash('success', 'Indisponibilité supprimée.');

        return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
    }
}
