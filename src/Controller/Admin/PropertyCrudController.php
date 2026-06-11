<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Property;
use App\Form\PropertyType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/properties')]
#[IsGranted('ROLE_ADMIN')]
final class PropertyCrudController extends AbstractController
{
    #[Route('', name: 'admin_property_index', methods: ['GET'])]
    public function index(PropertyRepository $repo): Response
    {
        return $this->render('admin/property/index.html.twig', [
            'properties' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_property_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $property = new Property();
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($property);
            $em->flush();
            $this->addFlash('success', 'Logement créé.');

            return $this->redirectToRoute('admin_property_index');
        }

        return $this->render('admin/property/form.html.twig', [
            'form' => $form,
            'heading' => 'Nouveau logement',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_property_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Property $property, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Logement mis à jour.');

            return $this->redirectToRoute('admin_property_index');
        }

        return $this->render('admin/property/form.html.twig', [
            'form' => $form,
            'heading' => 'Modifier le logement',
        ]);
    }

    #[Route('/{id}', name: 'admin_property_delete', methods: ['POST'])]
    public function delete(Request $request, Property $property, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$property->getId(), $request->request->get('_token'))) {
            $em->remove($property);
            $em->flush();
            $this->addFlash('success', 'Logement supprimé.');
        }

        return $this->redirectToRoute('admin_property_index');
    }
}
