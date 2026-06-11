<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\HostPropertyType;
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
    public function __construct(
        private readonly PropertyRepository $propertyRepo,
        private readonly ReservationRepository $reservationRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'host_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $properties = $this->propertyRepo->findBy(['host' => $user]);
        $reservations = $this->reservationRepo->findByHost($user);

        $pendingCount = count(array_filter($reservations, static fn (Reservation $r): bool => $r->getStatus() === 'pending'));
        $confirmedCount = count(array_filter($reservations, static fn (Reservation $r): bool => $r->getStatus() === 'confirmed'));

        return $this->render('host/dashboard.html.twig', [
            'properties' => $properties,
            'reservations' => $reservations,
            'pendingCount' => $pendingCount,
            'confirmedCount' => $confirmedCount,
        ]);
    }

    #[Route('/properties', name: 'host_properties', methods: ['GET'])]
    public function properties(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $properties = $this->propertyRepo->findBy(['host' => $user]);

        return $this->render('host/properties/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/properties/{id}', name: 'host_property_show', methods: ['GET'])]
    #[IsGranted('PROPERTY_EDIT', subject: 'property')]
    public function showProperty(Property $property): Response
    {
        $reservations = $this->reservationRepo->findBy(
            ['property' => $property],
            ['createdAt' => 'DESC']
        );

        return $this->render('host/properties/show.html.twig', [
            'property' => $property,
            'reservations' => $reservations,
        ]);
    }

    #[Route('/properties/{id}/edit', name: 'host_property_edit', methods: ['GET', 'POST'])]
    #[IsGranted('PROPERTY_EDIT', subject: 'property')]
    public function editProperty(Property $property, Request $request): Response
    {
        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Logement mis à jour avec succès.');

            return $this->redirectToRoute('host_properties');
        }

        return $this->render('host/properties/edit.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/properties/{id}/delete', name: 'host_property_delete', methods: ['POST'])]
    #[IsGranted('PROPERTY_DELETE', subject: 'property')]
    public function deleteProperty(Property $property, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_property_' . $property->getId(), $request->request->get('_token'))) {
            $this->em->remove($property);
            $this->em->flush();

            $this->addFlash('success', 'Logement supprimé.');
        }

        return $this->redirectToRoute('host_properties');
    }

    #[Route('/reservations', name: 'host_reservations', methods: ['GET'])]
    public function reservations(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $reservations = $this->reservationRepo->findByHost($user);

        return $this->render('host/reservations/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/reservations/{id}', name: 'host_reservation_show', methods: ['GET'])]
    public function showReservation(Reservation $reservation): Response
    {
        $property = $reservation->getProperty();
        if ($property === null || $property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas l\'hôte de ce logement.');
        }

        return $this->render('host/reservations/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/reservations/{id}/confirm', name: 'host_reservation_confirm', methods: ['POST'])]
    public function confirmReservation(Reservation $reservation, Request $request): Response
    {
        $property = $reservation->getProperty();
        if ($property === null || $property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('confirm_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            if ($reservation->getStatus() === 'pending') {
                $reservation->setStatus('confirmed');
                $reservation->setUpdatedAt(new \DateTimeImmutable());
                $this->em->flush();
                $this->addFlash('success', 'Réservation confirmée.');
            }
        }

        return $this->redirectToRoute('host_reservation_show', ['id' => $reservation->getId()]);
    }

    #[Route('/reservations/{id}/cancel', name: 'host_reservation_cancel', methods: ['POST'])]
    public function cancelReservation(Reservation $reservation, Request $request): Response
    {
        $property = $reservation->getProperty();
        if ($property === null || $property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('cancel_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            if (in_array($reservation->getStatus(), ['pending', 'confirmed'], true)) {
                $reservation->setStatus('cancelled');
                $reservation->setUpdatedAt(new \DateTimeImmutable());
                $this->em->flush();
                $this->addFlash('success', 'Réservation annulée.');
            }
        }

        return $this->redirectToRoute('host_reservation_show', ['id' => $reservation->getId()]);
    }
}
