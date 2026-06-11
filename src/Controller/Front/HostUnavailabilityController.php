<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use App\Entity\User;
use App\Form\PropertyUnavailabilityType;
use App\Repository\PropertyRepository;
use App\Repository\PropertyUnavailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/host/properties/{propertyId}/unavailability', name: 'app_host_unavailability')]
final class HostUnavailabilityController extends AbstractController
{
    /**
     * List unavailability periods for a property.
     */
    #[Route('', name: 'app_host_unavailability_list', methods: ['GET'])]
    public function list(
        string $propertyId,
        PropertyRepository $propertyRepository,
        PropertyUnavailabilityRepository $unavailabilityRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        $unavailabilities = $unavailabilityRepository->findByProperty($property);

        return $this->render('front/host/unavailability/list.html.twig', [
            'property' => $property,
            'unavailabilities' => $unavailabilities,
        ]);
    }

    /**
     * Create a new unavailability period.
     */
    #[Route('/new', name: 'app_host_unavailability_new', methods: ['GET', 'POST'])]
    public function new(
        string $propertyId,
        Request $request,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        $unavailability = new PropertyUnavailability();
        $form = $this->createForm(PropertyUnavailabilityType::class, $unavailability);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $unavailability->setProperty($property);
            $entityManager->persist($unavailability);
            $entityManager->flush();

            $this->addFlash('success', 'Période d\'indisponibilité créée avec succès.');

            return $this->redirectToRoute('app_host_unavailability_list', ['propertyId' => $propertyId]);
        }

        return $this->render('front/host/unavailability/form.html.twig', [
            'property' => $property,
            'form' => $form,
            'unavailability' => $unavailability,
        ]);
    }

    /**
     * Edit an unavailability period.
     */
    #[Route('/{id}/edit', name: 'app_host_unavailability_edit', methods: ['GET', 'POST'])]
    public function edit(
        string $propertyId,
        PropertyUnavailability $unavailability,
        Request $request,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        if ($unavailability->getProperty()->getId() !== $property->getId()) {
            throw $this->createAccessDeniedException('Unavailability does not belong to this property.');
        }

        $form = $this->createForm(PropertyUnavailabilityType::class, $unavailability);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $unavailability->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Période d\'indisponibilité mise à jour avec succès.');

            return $this->redirectToRoute('app_host_unavailability_list', ['propertyId' => $propertyId]);
        }

        return $this->render('front/host/unavailability/form.html.twig', [
            'property' => $property,
            'form' => $form,
            'unavailability' => $unavailability,
        ]);
    }

    /**
     * Delete an unavailability period.
     */
    #[Route('/{id}/delete', name: 'app_host_unavailability_delete', methods: ['POST'])]
    public function delete(
        string $propertyId,
        PropertyUnavailability $unavailability,
        Request $request,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        if ($unavailability->getProperty()->getId() !== $property->getId()) {
            throw $this->createAccessDeniedException('Unavailability does not belong to this property.');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('delete' . $unavailability->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($unavailability);
        $entityManager->flush();

        $this->addFlash('success', 'Période d\'indisponibilité supprimée avec succès.');

        return $this->redirectToRoute('app_host_unavailability_list', ['propertyId' => $propertyId]);
    }
}
