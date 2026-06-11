<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Form\PropertyType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Form\HostPropertyType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/property')]
final class PropertyController extends AbstractController
{
    #[Route(name: 'app_property_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, PropertyRepository $propertyRepository): Response
    {
        $statusFilter = $request->query->getString('status');
        $statusFilter = $statusFilter !== '' ? $statusFilter : null;
        $properties = $propertyRepository->findForListing($statusFilter);

        return $this->render('property/index.html.twig', [
            'properties' => $properties,
            'total' => $propertyRepository->countAll(),
            'published' => $propertyRepository->countByStatus('published'),
            'pending' => $propertyRepository->countByStatus('pending'),
            'statusFilter' => $statusFilter,
        ]);
    }

    #[Route('/new', name: 'app_property_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $property = new Property();
        $property->setHost($user);
        $property->setStatus('draft');

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($user instanceof \App\Entity\User && !$user->hasAssignedRole('ROLE_HOST')) {
                $user->addAssignedRole('ROLE_HOST');
                $entityManager->persist($user);
            }

            $entityManager->persist($property);
            $entityManager->flush();

            $this->addFlash('success', 'Votre logement a été créé en tant que brouillon. Vous êtes maintenant un hôte !');

            return $this->redirectToRoute('host_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('host/properties/new.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_property_show', methods: ['GET'])]
    public function show(Property $property, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        return $this->render('property/show.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_property_edit', methods: ['GET', 'POST'])]
    #[IsGranted('PROPERTY_EDIT', subject: 'property')]
    public function edit(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Annonce mise à jour avec succès.');

            return $this->redirectToRoute('host_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('property/edit.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_property_delete', methods: ['POST'])]
    #[IsGranted('PROPERTY_DELETE', subject: 'property')]
    public function delete(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($property);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce supprimée.');
        }

        return $this->redirectToRoute('host_dashboard', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/publish', name: 'app_property_publish', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function publish(Request $request, Property $property, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($this->isCsrfTokenValid('publish'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $errors = $validator->validate($property, null, ['publish']);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            } else {
                $property->setStatus('published');
                $property->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();
                $this->addFlash('success', 'Annonce publiée.');
            }
        }

        $redirect = $request->headers->get('referer');

        return $redirect
            ? $this->redirect($redirect)
            : $this->redirectToRoute('app_property_show', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reject', name: 'app_property_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('reject'.$property->getId(), $request->getPayload()->getString('_token'))) {
            $property->setStatus('draft');
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Annonce refusée et repassée en brouillon.');
        }

        $redirect = $request->headers->get('referer');

        return $redirect
            ? $this->redirect($redirect)
            : $this->redirectToRoute('app_property_show', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
    }
}
