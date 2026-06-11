<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_HOST')]
final class ICalController extends AbstractController
{
    #[Route('/host/property/{id}/ical-token', name: 'app_host_ical_token_refresh', methods: ['POST'])]
    #[Route('/compte/hote/logements/{id}/calendrier/ical-token', name: 'app_host_ical_token_refresh_legacy', methods: ['POST'])]
    public function refreshIcalToken(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwner($property);

        if (!$this->isCsrfTokenValid('refresh_ical_token'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $property->refreshIcalToken();
        $entityManager->flush();
        $this->addFlash('success', 'Le lien iCal a été régénéré.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/compte/hote/logements/{id}/calendrier/ical-import', name: 'app_host_ical_import_update', methods: ['POST'])]
    public function updateIcalImportUrl(Request $request, Property $property, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwner($property);

        if (!$this->isCsrfTokenValid('update_ical_import'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $url = trim((string) $request->request->get('external_ical_url', ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->addFlash('error', 'L’URL iCal externe est invalide.');
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $property->setExternalIcalUrl($url !== '' ? $url : null);
        $entityManager->flush();
        $this->addFlash('success', $url !== '' ? 'L’URL iCal externe a été enregistrée.' : 'L’URL iCal externe a été supprimée.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    private function denyUnlessOwner(Property $property): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
