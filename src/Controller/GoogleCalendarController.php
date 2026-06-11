<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Service\GoogleCalendarOAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
final class GoogleCalendarController extends AbstractController
{
    #[Route('/property/{id}/connect-google', name: 'app_host_connect_google', methods: ['GET'])]
    public function connect(
        Property $property,
        GoogleCalendarOAuthService $oauthService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $authUrl = $oauthService->getAuthUrl($property);

        return $this->redirect($authUrl);
    }

    #[Route('/connect/google/callback', name: 'app_google_callback', methods: ['GET'])]
    public function callback(
        Request $request,
        GoogleCalendarOAuthService $oauthService,
        EntityManagerInterface $entityManager,
    ): Response {
        $error = $request->query->get('error');
        if ($error !== null) {
            $this->addFlash('error', 'Autorisation refusée : ' . $error);
            return $this->redirectToRoute('app_host_dashboard');
        }

        $code = $request->query->get('code');
        $state = $request->query->get('state'); // property UUID

        if ($code === null || $state === null) {
            $this->addFlash('error', 'Paramètres OAuth manquants.');
            return $this->redirectToRoute('app_host_dashboard');
        }

        $property = $entityManager->getRepository(Property::class)->find($state);
        if ($property === null) {
            $this->addFlash('error', 'Logement introuvable.');
            return $this->redirectToRoute('app_host_dashboard');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost() !== $user) {
            throw $this->createAccessDeniedException();
        }

        try {
            $oauthService->handleCallback($code, $property);
            $this->addFlash('success', 'Calendrier Google connecté avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur de connexion : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
    }

    #[Route('/property/{id}/regenerate-ical-token', name: 'app_host_regenerate_ical_token', methods: ['POST'])]
    public function regenerateIcalToken(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('regenerate_ical_' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
        }

        $property->regenerateIcalToken();
        $entityManager->flush();

        $this->addFlash('success', 'Token iCal regénéré. L\'ancienne URL n\'est plus valide.');

        return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
    }

    #[Route('/property/{id}/disconnect-google', name: 'app_host_disconnect_google', methods: ['POST'])]
    public function disconnect(
        Request $request,
        Property $property,
        GoogleCalendarOAuthService $oauthService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('disconnect_google' . $property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
        }

        $sync = $property->getGoogleCalendarSync();
        if ($sync !== null) {
            $oauthService->disconnect($sync);
            $this->addFlash('success', 'Calendrier Google déconnecté.');
        }

        return $this->redirectToRoute('app_host_availability', ['id' => $property->getId()]);
    }
}
