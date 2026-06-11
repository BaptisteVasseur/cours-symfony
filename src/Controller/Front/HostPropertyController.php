<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyICalSync;
use App\Form\PropertyListingType;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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

    #[Route('/ical/generer', name: 'app_host_ical_generate', methods: ['POST'])]
    public function generateIcalToken(Property $property, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('ical-' . $property->getId(), $request->request->getString('_token'))) {
            $property->setIcalToken(bin2hex(random_bytes(20)));
            $entityManager->flush();
            $this->addFlash('success', 'Lien de synchronisation iCal généré.');
        }

        return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
    }

    #[Route('/ical/revoquer', name: 'app_host_ical_revoke', methods: ['POST'])]
    public function revokeIcalToken(Property $property, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('ical-' . $property->getId(), $request->request->getString('_token'))) {
            $property->setIcalToken(null);
            $entityManager->flush();
            $this->addFlash('success', 'Lien de synchronisation iCal révoqué.');
        }

        return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
    }

    #[Route('/ical/import/ajouter', name: 'app_host_ical_import_add', methods: ['POST'])]
    public function addImportFeed(Property $property, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('ical-import-' . $property->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
        }

        $providerName = trim($request->request->getString('providerName'));
        $url = trim($request->request->getString('icalUrl'));

        if ($providerName === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->addFlash('error', 'Renseignez un nom et une URL iCal valide.');

            return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
        }

        $sync = new PropertyICalSync();
        $sync->setProperty($property);
        $sync->setProviderName($providerName);
        $sync->setICalUrl($url);
        $entityManager->persist($sync);
        $entityManager->flush();

        $this->addFlash('success', 'Calendrier externe ajouté. Il sera synchronisé au prochain passage (app:ical:sync).');

        return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
    }

    #[Route('/ical/import/{syncId}/supprimer', name: 'app_host_ical_import_remove', methods: ['POST'])]
    public function removeImportFeed(
        Property $property,
        #[MapEntity(id: 'syncId')] PropertyICalSync $sync,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($sync->getProperty() !== $property) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('ical-import-del-' . $sync->getId(), $request->request->getString('_token'))) {
            // Les blocages importés par ce flux sont supprimés en cascade (FK onDelete: CASCADE).
            $entityManager->remove($sync);
            $entityManager->flush();
            $this->addFlash('success', 'Calendrier externe supprimé.');
        }

        return $this->redirectToRoute('app_host_property_edit', ['id' => $property->getId()]);
    }
}
