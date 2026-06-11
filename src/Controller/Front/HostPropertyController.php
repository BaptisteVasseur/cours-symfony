<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Form\PropertyListingType;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes/{id}')]
#[IsGranted('ROLE_USER')]
#[IsGranted(PropertyVoter::EDIT, subject: 'property')]
final class HostPropertyController extends AbstractController
{
    #[Route('/modifier', name: 'app_host_property_edit', methods: ['GET', 'POST'])]
    public function edit(Property $property, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PropertyListingType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Annonce mise à jour.');

            return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
        }

        return $this->render('front/host/property_edit.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
