<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Property;
use App\Entity\PropertyMedia;
use App\Form\PropertyFormType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/properties')]
final class PropertyCrudController extends AbstractController
{
    #[Route('', name: 'admin_property_index', methods: ['GET'])]
    public function index(Request $request, PropertyRepository $propertyRepository): Response
    {
        $status = $request->query->getString('status');

        return $this->render('admin/property/index.html.twig', [
            'properties' => $propertyRepository->findForListing($status !== '' ? $status : null),
        ]);
    }

    #[Route('/new', name: 'admin_property_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $property = new Property();
        $form = $this->createForm(PropertyFormType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($property);
            $this->handleImageUploads($form->get('imageFiles')->getData(), $property, $entityManager);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce créée.');

            return $this->redirectToRoute('admin_property_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/property/form.html.twig', [
            'form' => $form,
            'property' => $property,
            'title' => 'Nouvelle annonce',
            'button_label' => 'Créer',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_property_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PropertyFormType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $this->handleImageUploads($form->get('imageFiles')->getData(), $property, $entityManager);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce mise à jour.');

            return $this->redirectToRoute('admin_property_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/property/form.html.twig', [
            'form' => $form,
            'property' => $property,
            'title' => 'Modifier l\'annonce',
            'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_property_delete', methods: ['POST'])]
    public function delete(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $property->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($property);
        $entityManager->flush();
        $this->addFlash('success', 'Annonce supprimée.');

        return $this->redirectToRoute('admin_property_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/publish', name: 'admin_property_publish', methods: ['POST'])]
    public function publish(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('publish' . $property->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $property
            ->setStatus('published')
            ->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();
        $this->addFlash('success', 'Annonce publiée.');

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('admin_property_index'));
    }

    #[Route('/{id}/reject', name: 'admin_property_reject', methods: ['POST'])]
    public function reject(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('reject' . $property->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $property
            ->setStatus('draft')
            ->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();
        $this->addFlash('success', 'Annonce refusée.');

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('admin_property_index'));
    }

    private function handleImageUploads(?array $uploadedFiles, Property $property, EntityManagerInterface $entityManager): void
    {
        if ($uploadedFiles === null) {
            return;
        }

        $directory = (string) $this->getParameter('property_images_directory');
        $sortOrder = $property->getMedia()->count();

        foreach ($uploadedFiles as $uploadedFile) {
            if (!$uploadedFile instanceof UploadedFile) {
                continue;
            }

            $filename = uniqid('property_', true) . '.' . ($uploadedFile->guessExtension() ?: 'jpg');
            $uploadedFile->move($directory, $filename);

            $image = new PropertyMedia();
            $image
                ->setMediaType('image')
                ->setFileUrl('/uploads/property-images/' . $filename)
                ->setSortOrder($sortOrder++)
                ->setIsCover($property->getMedia()->isEmpty());

            $property->addMedium($image);
            $entityManager->persist($image);
        }
    }
}
