<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/logements/{id}/export-ical')]
#[IsGranted('ROLE_USER')]
final class IcalExportController extends AbstractController
{
    #[Route('/generer', name: 'app_host_ical_export_generate', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function generate(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('ical_export_generate_' . $property->getId(), (string) $request->request->get('_token'))) {
            $hadToken = $property->getIcalExportToken() !== null;
            $property->setIcalExportToken(bin2hex(random_bytes(32)));
            $entityManager->flush();

            $this->addFlash('success', $hadToken
                ? 'Lien de synchronisation iCal régénéré. L\'ancien lien ne fonctionne plus.'
                : 'Lien de synchronisation iCal généré.');
        }

        return $this->redirectToRoute('app_host_availability_index', ['id' => $property->getId()]);
    }

    #[Route('/revoquer', name: 'app_host_ical_export_revoke', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function revoke(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('ical_export_revoke_' . $property->getId(), (string) $request->request->get('_token'))) {
            $property->setIcalExportToken(null);
            $entityManager->flush();

            $this->addFlash('success', 'Lien de synchronisation iCal révoqué.');
        }

        return $this->redirectToRoute('app_host_availability_index', ['id' => $property->getId()]);
    }
}
