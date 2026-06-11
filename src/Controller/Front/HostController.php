<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\ReservationStatusHistory;
use App\Entity\User;
use App\Form\HostPropertyType;
use App\Form\UnavailabilityType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/hote')]
#[IsGranted('ROLE_HOST')]
final class HostController extends AbstractController
{
    #[Route('/logements/nouveau', name: 'app_host_property_new', methods: ['GET', 'POST'])]
    public function newProperty(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = new Property();
        $property->setHost($user);
        $property->setStatus('published');

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($property);
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a été publiée.');

            return $this->redirectToRoute('app_host_property_unavailability', ['id' => $property->getId()]);
        }

        return $this->render('front/host/property/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/logements/{id}/modifier', name: 'app_host_property_edit', methods: ['GET', 'POST'])]
    public function editProperty(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Annonce mise à jour.');

            return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
        }

        return $this->render('front/host/property/edit.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/logements/{id}/indisponibilites', name: 'app_host_property_unavailability', methods: ['GET', 'POST'])]
    public function manageUnavailability(
        Request $request,
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $availability = new PropertyAvailability();
        $availability->setIsAvailable(false);
        $availability->setProperty($property);

        $form = $this->createForm(UnavailabilityType::class, $availability);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($availability);
            $entityManager->flush();

            $this->addFlash('success', 'Date bloquée ajoutée.');

            return $this->redirectToRoute('app_host_property_unavailability', ['id' => $property->getId()]);
        }

        return $this->render('front/host/property/unavailability.html.twig', [
            'property'       => $property,
            'form'           => $form,
            'unavailabilities' => $availabilityRepository->findByProperty($property),
        ]);
    }

    #[Route('/logements/{id}/indisponibilites/{availId}/supprimer', name: 'app_host_unavailability_delete', methods: ['POST'])]
    public function deleteUnavailability(
        Request $request,
        Property $property,
        int $availId,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $availability = $availabilityRepository->find($availId);
        if ($availability && $this->isCsrfTokenValid('delete_unavailability'.$availId, $request->getPayload()->getString('_token'))) {
            $entityManager->remove($availability);
            $entityManager->flush();
            $this->addFlash('success', 'Date supprimée.');
        }

        return $this->redirectToRoute('app_host_property_unavailability', ['id' => $property->getId()]);
    }

    #[Route('/logements/{id}/bloquer', name: 'app_host_block_dates', methods: ['POST'])]
    public function blockDates(
        Request $request,
        Property $property,
        ReservationRepository $reservationRepository,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('block_dates'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $request->getPayload()->getString('startDate'));
        $end   = \DateTimeImmutable::createFromFormat('Y-m-d', $request->getPayload()->getString('endDate'));

        if (!$start || !$end || $start > $end) {
            $this->addFlash('error', 'Dates invalides.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        if ($reservationRepository->hasConfirmedOverlap($property, $start, $end->modify('+1 day'))) {
            $this->addFlash('error', 'Une réservation confirmée existe déjà sur cette période.');

            return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
        }

        $current = $start;
        while ($current <= $end) {
            $existing = $availabilityRepository->findOneBy(['property' => $property, 'availableDate' => $current]);
            if ($existing) {
                $existing->setIsAvailable(false);
            } else {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($current);
                $availability->setIsAvailable(false);
                $entityManager->persist($availability);
            }
            $current = $current->modify('+1 day');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Indisponibilité enregistrée.');

        return $this->redirectToRoute('app_logement_detail', ['id' => $property->getId()]);
    }

    #[Route('/moderation', name: 'app_host_moderation', methods: ['GET'])]
    public function moderation(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $all = $reservationRepository->findAllByHost($user);

        $pending   = array_values(array_filter($all, fn ($r) => $r->getStatus() === 'pending'));
        $confirmed = array_values(array_filter($all, fn ($r) => $r->getStatus() === 'confirmed'));
        $others    = array_values(array_filter($all, fn ($r) => !in_array($r->getStatus(), ['pending', 'confirmed'], true)));

        return $this->render('front/host/moderation.html.twig', [
            'pending'   => $pending,
            'confirmed' => $confirmed,
            'others'    => $others,
        ]);
    }

    #[Route('/moderation/{id}/accepter', name: 'app_host_reservation_accept', methods: ['POST'])]
    public function acceptReservation(
        Request $request,
        \App\Entity\Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation n\'est plus en attente.');

            return $this->redirectToRoute('app_host_moderation');
        }

        if (!$this->isCsrfTokenValid('accept'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('app_host_moderation');
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('confirmed');
        $history->setChangedBy($user);

        $reservation->setStatus('confirmed');
        $entityManager->persist($history);
        $entityManager->flush();

        $this->addFlash('success', 'Réservation confirmée.');

        return $this->redirectToRoute('app_host_moderation');
    }

    #[Route('/moderation/{id}/refuser', name: 'app_host_reservation_refuse', methods: ['POST'])]
    public function refuseReservation(
        Request $request,
        \App\Entity\Reservation $reservation,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($reservation->getProperty()?->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette réservation n\'est plus en attente.');

            return $this->redirectToRoute('app_host_moderation');
        }

        if (!$this->isCsrfTokenValid('refuse'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('app_host_moderation');
        }

        $reason = trim($request->getPayload()->getString('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');

            return $this->redirectToRoute('app_host_moderation');
        }

        $history = new ReservationStatusHistory();
        $history->setReservation($reservation);
        $history->setOldStatus('pending');
        $history->setNewStatus('cancelled');
        $history->setChangedBy($user);

        $reservation->setStatus('cancelled');
        $reservation->setCancellationReason($reason);
        $entityManager->persist($history);
        $entityManager->flush();

        $this->addFlash('success', 'Réservation refusée.');

        return $this->redirectToRoute('app_host_moderation');
    }
}
