<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\PropertyVoter;

#[Route('/host/logement')]
#[IsGranted('ROLE_USER')]
final class HostPropertyController extends AbstractController
{
    #[Route('/{id}/disponibilites', name: 'app_host_property_availability', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::MANAGE, subject: 'property')]
    public function availability(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
        PropertyAvailabilityRepository $availRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $startDate = new \DateTimeImmutable($request->request->get('start_date'));
            $endDate = new \DateTimeImmutable($request->request->get('end_date'));
            $reason = $request->request->get('reason', 'Bloqué manuellement');

            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setStartDate($startDate);
            $availability->setEndDate($endDate);
            $availability->setIsAvailable(false); // On bloque
            $availability->setNotes($reason);

            $entityManager->persist($availability);
            $entityManager->flush();

            $this->addFlash('success', 'Période bloquée avec succès.');
            return $this->redirectToRoute('app_host_property_availability', ['id' => $property->getId()]);
        }

        return $this->render('front/host/availability.html.twig', [
            'property' => $property,
            'availabilities' => $availRepository->findBy(['property' => $property]),
        ]);
    }
}
