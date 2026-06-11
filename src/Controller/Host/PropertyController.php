<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\User;
use App\Form\HostPropertyType;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/logements')]
#[IsGranted('ROLE_HOST')]
final class PropertyController extends AbstractController
{
    #[Route('/nouveau', name: 'app_host_property_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = new Property();
        $property->setHost($user);
        $property->setStatus('pending');
        $property->setAddress(new PropertyAddress());

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($property);
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a été créée et sera publiée après validation.');

            return $this->redirectToRoute('app_account_properties');
        }

        return $this->render('host/property/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_host_property_edit', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function edit(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($property->getAddress() === null) {
            $property->setAddress(new PropertyAddress());
        }

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a été mise à jour.');

            return $this->redirectToRoute('app_account_properties');
        }

        return $this->render('host/property/edit.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
