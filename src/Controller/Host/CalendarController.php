<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\PropertyUnavailability;
use App\Entity\User;
use App\Form\BlockDateType;
use App\Repository\PropertyRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\PropertyVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
final class CalendarController extends AbstractController
{
    #[Route('/properties/{id}/calendar', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function show(
        Property $property,
        PropertyRepository $propertyRepository,
        AvailabilityService $availabilityService,
        EntityManagerInterface $entityManager,
    ): Response {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $property->ensureIcalToken();
        $entityManager->flush();

        $blockForm = $this->createForm(BlockDateType::class);

        return $this->render('host/calendar/show.html.twig', [
            'property' => $property,
            'blockedRanges' => $availabilityService->getBlockedRanges($property),
            'blockForm' => $blockForm->createView(),
        ]);
    }

    #[Route('/properties/{id}/block', name: 'app_host_calendar_block', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function block(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(BlockDateType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Veuillez vérifier les dates saisies.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $data = $form->getData();
        $startDate = $data['startDate'];
        $endDate = $data['endDate'];

        if ($startDate >= $endDate) {
            $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');

            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $unavailability = new PropertyUnavailability();
        $unavailability->setProperty($property);
        $unavailability->setStartDate($startDate);
        $unavailability->setEndDate($endDate);
        $unavailability->setReason($data['reason'] ?? null);

        $entityManager->persist($unavailability);
        $entityManager->flush();

        $this->addFlash('success', 'Période bloquée avec succès.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }
}
