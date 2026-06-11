<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyGoogleCalendarSync;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class GoogleCalendarOAuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(GOOGLE_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%env(GOOGLE_REDIRECT_URI)%')]
        private string $redirectUri,
    ) {
    }

    public function getClient(): \Google\Client
    {
        $client = new \Google\Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->addScope(\Google\Service\Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    public function getAuthUrl(Property $property): string
    {
        $client = $this->getClient();
        $client->setState((string) $property->getId());

        return $client->createAuthUrl();
    }

    public function handleCallback(string $authorizationCode, Property $property): PropertyGoogleCalendarSync
    {
        $client = $this->getClient();
        $token = $client->fetchAccessTokenWithAuthCode($authorizationCode);

        if (isset($token['error'])) {
            throw new \RuntimeException('Erreur OAuth : ' . ($token['error_description'] ?? $token['error']));
        }

        $sync = $property->getGoogleCalendarSync();
        if ($sync === null) {
            $sync = new PropertyGoogleCalendarSync();
            $sync->setProperty($property);
            $this->entityManager->persist($sync);
        }

        $sync->setAccessToken($token['access_token']);
        $sync->setRefreshToken($token['refresh_token'] ?? $sync->getRefreshToken());

        if (isset($token['expires_in'])) {
            $expiresAt = new \DateTimeImmutable('+' . $token['expires_in'] . ' seconds');
            $sync->setTokenExpiresAt($expiresAt);
        }

        $sync->setSyncEnabled(true);
        $sync->setLastError(null);
        $sync->setUpdatedAt(new \DateTimeImmutable());

        // Set the primary calendar as default
        $calendarService = new \Google\Service\Calendar($client);
        $calendarList = $calendarService->calendarList->listCalendarList();
        $primaryCalendarId = null;
        foreach ($calendarList->getItems() as $cal) {
            if ($cal->getPrimary()) {
                $primaryCalendarId = $cal->getId();
                break;
            }
        }
        // Fallback au premier calendrier si aucun primary trouvé
        if ($primaryCalendarId === null && count($calendarList->getItems()) > 0) {
            $primaryCalendarId = $calendarList->getItems()[0]->getId();
        }
        $sync->setGoogleCalendarId($primaryCalendarId);

        $this->entityManager->flush();

        return $sync;
    }

    public function refreshTokenIfNeeded(PropertyGoogleCalendarSync $sync): void
    {
        if (!$sync->isTokenExpired()) {
            return;
        }

        if ($sync->getRefreshToken() === null) {
            $sync->setSyncEnabled(false);
            $sync->setLastError('Refresh token manquant, veuillez reconnecter votre calendrier.');
            $this->entityManager->flush();
            return;
        }

        $client = $this->getClient();
        $client->fetchAccessTokenWithRefreshToken($sync->getRefreshToken());
        $accessToken = $client->getAccessToken();

        if (isset($accessToken['error'])) {
            $sync->setSyncEnabled(false);
            $sync->setLastError('Token expiré, veuillez reconnecter votre calendrier.');
            $this->entityManager->flush();
            return;
        }

        $sync->setAccessToken($accessToken['access_token']);
        if (isset($accessToken['expires_in'])) {
            $expiresAt = new \DateTimeImmutable('+' . $accessToken['expires_in'] . ' seconds');
            $sync->setTokenExpiresAt($expiresAt);
        }
        $sync->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function disconnect(PropertyGoogleCalendarSync $sync): void
    {
        try {
            $client = $this->getClient();
            $client->setAccessToken($sync->getAccessToken());
            $client->revokeToken();
        } catch (\Exception) {
            // Even if revoke fails, we still clean up locally
        }

        $sync->setAccessToken(null);
        $sync->setRefreshToken(null);
        $sync->setTokenExpiresAt(null);
        $sync->setGoogleCalendarId(null);
        $sync->setSyncEnabled(false);
        $sync->setLastSyncAt(null);
        $sync->setLastError(null);
        $sync->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
