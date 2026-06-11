<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Form\ListingType;
use App\Repository\ListingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/listing')]
#[IsGranted('ROLE_USER')]
class ListingController extends AbstractController
{

    #[Route('', name: 'app_listing_index', methods: ['GET'])]
    public function index(ListingRepository $listingRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $listings = $listingRepository->findBy([], ['createdAt' => 'DESC']);
        } else {
            $listings = $listingRepository->findBy(['host' => $this->getUser()], ['createdAt' => 'DESC']);
        }

        return $this->render('listing/index.html.twig', [
            'listings' => $listings,
        ]);
    }

    #[Route('/new', name: 'app_listing_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $listing = new Listing();
        $listing->setHost($this->getUser());
        $listing->setCreatedAt(new \DateTimeImmutable());
        $listing->setUpdatedAt(new \DateTimeImmutable());

        $form = $this->createForm(ListingType::class, $listing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $listing->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($listing);
            $em->flush();

            $this->addFlash('success', 'Logement créé avec succès.');

            return $this->redirectToRoute('app_listing_index');
        }

        return $this->render('listing/new.html.twig', [
            'listing' => $listing,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_listing_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function show(Listing $listing): Response
    {
        return $this->render('listing/show.html.twig', [
            'listing' => $listing,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_listing_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function edit(Request $request, Listing $listing, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwnerOrAdmin($listing);

        $form = $this->createForm(ListingType::class, $listing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $listing->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', 'Logement mis à jour.');

            return $this->redirectToRoute('app_listing_index');
        }

        return $this->render('listing/edit.html.twig', [
            'listing' => $listing,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_listing_delete', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function delete(Request $request, Listing $listing, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwnerOrAdmin($listing);

        if ($this->isCsrfTokenValid('delete' . $listing->getId(), $request->request->get('_token'))) {
            $em->remove($listing);
            $em->flush();
            $this->addFlash('success', 'Logement supprimé.');
        }

        return $this->redirectToRoute('app_listing_index');
    }


    private function denyAccessUnlessOwnerOrAdmin(Listing $listing): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && $listing->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez gérer que vos propres logements.');
        }
    }
}
