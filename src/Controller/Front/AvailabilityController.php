<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/logement/{id}/disponibilites')]
#[IsGranted('ROLE_HOST')]
final class AvailabilityController extends AbstractController
{
    #[Route('', name: 'app_availability_index', methods: ['GET'])]
    public function index(Property $property, PropertyAvailabilityRepository $repo): Response
    {
        $blocked = $repo->findBy(['property' => $property, 'isAvailable' => false], ['availableDate' => 'ASC']);

        return $this->render('front/account/availability.html.twig', [
            'property' => $property,
            'blocked' => $blocked,
        ]);
    }

    #[Route('/bloquer', name: 'app_availability_block', methods: ['POST'])]
    public function block(Request $request, Property $property, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $startStr = $request->request->get('start_date');
        $endStr = $request->request->get('end_date');

        if (!$startStr || !$endStr) {
            $this->addFlash('error', 'Dates invalides.');
            return $this->redirectToRoute('app_availability_index', ['id' => $property->getId()]);
        }

        $start = new \DateTimeImmutable($startStr);
        $end = new \DateTimeImmutable($endStr);
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);

        $reason = $request->request->get('reason', 'Indisponibilité');

        foreach ($period as $date) {
            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setAvailableDate(\DateTimeImmutable::createFromInterface($date));
            $availability->setIsAvailable(false);
            $availability->setReason($reason);
            $em->persist($availability);
        }

        $em->flush();
        $this->addFlash('success', 'Période bloquée avec succès.');

        return $this->redirectToRoute('app_availability_index', ['id' => $property->getId()]);
    }

    #[Route('/{availabilityId}/debloquer', name: 'app_availability_unblock', methods: ['POST'])]
    public function unblock(Request $request, Property $property, string $availabilityId, PropertyAvailabilityRepository $repo, EntityManagerInterface $em): Response
    {
        $availability = $repo->find($availabilityId);
        if ($availability && $availability->getProperty() === $property) {
            $em->remove($availability);
            $em->flush();
            $this->addFlash('success', 'Date débloquée.');
        }

        return $this->redirectToRoute('app_availability_index', ['id' => $property->getId()]);
    }
}