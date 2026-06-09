<?php

namespace App\Controller;

use App\Entity\Property;
use App\Form\BookingType;
use App\Form\PropertyType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/properties')]
class PropertyController extends AbstractController
{
    #[Route('', name: 'app_property_index')]
    public function index(Request $request, PropertyRepository $repo): Response
    {
        $city = $request->query->get('city');
        $guests = $request->query->getInt('guests') ?: null;

        return $this->render('property/index.html.twig', [
            'properties' => $repo->findPublished($city, $guests),
            'city' => $city,
            'guests' => $guests,
        ]);
    }

    #[Route('/new', name: 'app_property_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_HOST')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $property = new Property();
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setHost($this->getUser());
            $em->persist($property);
            $em->flush();

            $this->addFlash('success', 'Logement publié avec succès.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        return $this->render('property/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_property_show')]
    public function show(Property $property): Response
    {
        $bookingForm = $this->createForm(BookingType::class);

        return $this->render('property/show.html.twig', [
            'property' => $property,
            'bookingForm' => $bookingForm,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_property_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_HOST')]
    public function edit(Property $property, Request $request, EntityManagerInterface $em): Response
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Logement mis à jour.');
            return $this->redirectToRoute('app_property_show', ['id' => $property->getId()]);
        }

        return $this->render('property/edit.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_property_delete', methods: ['POST'])]
    #[IsGranted('ROLE_HOST')]
    public function delete(Property $property, Request $request, EntityManagerInterface $em): Response
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $property->getId(), $request->request->get('_token'))) {
            $em->remove($property);
            $em->flush();
            $this->addFlash('success', 'Logement supprimé.');
        }

        return $this->redirectToRoute('app_home');
    }
}
