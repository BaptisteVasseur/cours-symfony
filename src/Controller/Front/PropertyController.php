<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyRule;
use App\Entity\User;
use App\Form\PropertyType;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes')]
#[IsGranted('ROLE_HOST')]
final class PropertyController extends AbstractController
{
    #[Route('/nouvelle', name: 'app_host_property_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = new Property();
        $property->setHost($user);
        $property->setStatus('draft');

        $address = new PropertyAddress();
        $property->setAddress($address);

        $rules = new PropertyRule();
        $property->setRules($rules);

        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($property);
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a été créée. Elle est en attente de validation.');

            return $this->redirectToRoute('app_account_properties');
        }

        return $this->render('front/property/new.html.twig', [
            'form' => $form,
            'property' => $property,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_host_property_edit', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function edit(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($property->getAddress() === null) {
            $property->setAddress(new PropertyAddress());
        }

        if ($property->getRules() === null) {
            $property->setRules(new PropertyRule());
        }

        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Annonce mise à jour.');

            return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
        }

        return $this->render('front/property/edit.html.twig', [
            'form' => $form,
            'property' => $property,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_host_property_delete', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function delete(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_property_' . $property->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($property);
            $entityManager->flush();
            $this->addFlash('success', 'L\'annonce a été supprimée.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_account_properties');
    }
}
