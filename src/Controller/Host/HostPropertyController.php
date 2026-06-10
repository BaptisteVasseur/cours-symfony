<?php

declare(strict_types=1);

namespace App\Controller\Host;

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

#[Route('/host/properties')]
#[IsGranted('ROLE_HOST')]
class HostPropertyController extends AbstractController
{
    #[Route('', name: 'app_host_property_index', methods: ['GET'])]
    public function index(PropertyRepository $propertyRepository): Response
    {
        $user = $this->getUser();
        $properties = $propertyRepository->findBy(['host' => $user], ['createdAt' => 'DESC']);

        return $this->render('host/property/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/new', name: 'app_host_property_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $property = new Property();
        $property->setHost($this->getUser());

        $form = $this->createForm(PropertyFormType::class, $property, [
            'show_owner' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($property);
            $this->handleImageUploads($form->get('imageFiles')->getData(), $property, $entityManager);
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a été créée et est en attente de modération.');

            return $this->redirectToRoute('app_host_property_index');
        }

        return $this->render('host/property/form.html.twig', [
            'form' => $form,
            'property' => $property,
            'title' => 'Nouvelle annonce',
            'button_label' => 'Créer',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_host_property_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette annonce.');
        }

        $form = $this->createForm(PropertyFormType::class, $property, [
            'show_owner' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $this->handleImageUploads($form->get('imageFiles')->getData(), $property, $entityManager);
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a été mise à jour.');

            return $this->redirectToRoute('app_host_property_index');
        }

        return $this->render('host/property/form.html.twig', [
            'form' => $form,
            'property' => $property,
            'title' => 'Modifier l\'annonce',
            'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_host_property_delete', methods: ['POST'])]
    public function delete(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cette annonce.');
        }

        if ($this->isCsrfTokenValid('delete' . $property->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($property);
            $entityManager->flush();
            $this->addFlash('success', 'L\'annonce a été supprimée.');
        }

        return $this->redirectToRoute('app_host_property_index');
    }

    #[Route('/{id}/publish', name: 'app_host_property_publish', methods: ['POST'])]
    public function publish(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette annonce.');
        }

        if ($this->isCsrfTokenValid('publish' . $property->getId(), $request->getPayload()->getString('_token'))) {
            $property->setStatus('published');
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'L\'annonce a été publiée.');
        }

        return $this->redirectToRoute('app_host_property_index');
    }

    #[Route('/{id}/draft', name: 'app_host_property_draft', methods: ['POST'])]
    public function draft(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette annonce.');
        }

        if ($this->isCsrfTokenValid('draft' . $property->getId(), $request->getPayload()->getString('_token'))) {
            $property->setStatus('draft');
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'L\'annonce a été retirée de la publication (brouillon).');
        }

        return $this->redirectToRoute('app_host_property_index');
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
