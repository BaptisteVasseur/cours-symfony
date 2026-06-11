<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyICalToken;
use App\Entity\User;
use App\Repository\PropertyICalTokenRepository;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/host/properties/{propertyId}/ical-tokens', name: 'app_host_ical_tokens')]
final class HostICalTokenController extends AbstractController
{
    /**
     * List iCal tokens for a property.
     */
    #[Route('', name: 'app_host_ical_tokens_list', methods: ['GET'])]
    public function list(
        string $propertyId,
        PropertyRepository $propertyRepository,
        PropertyICalTokenRepository $tokenRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        $tokens = $tokenRepository->findValidTokensByProperty($property);

        return $this->render('front/host/ical_tokens/list.html.twig', [
            'property' => $property,
            'tokens' => $tokens,
        ]);
    }

    /**
     * Create a new iCal token.
     */
    #[Route('/new', name: 'app_host_ical_tokens_new', methods: ['POST'])]
    public function new(
        string $propertyId,
        Request $request,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('create_ical_token', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $token = new PropertyICalToken();
        $token->setProperty($property);
        $entityManager->persist($token);
        $entityManager->flush();

        $this->addFlash('success', 'Nouveau token iCal créé avec succès.');

        return $this->redirectToRoute('app_host_ical_tokens_list', ['propertyId' => $propertyId]);
    }

    /**
     * Revoke an iCal token.
     */
    #[Route('/{id}/revoke', name: 'app_host_ical_tokens_revoke', methods: ['POST'])]
    public function revoke(
        string $propertyId,
        PropertyICalToken $token,
        Request $request,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        if ($token->getProperty()->getId() !== $property->getId()) {
            throw $this->createAccessDeniedException('Token does not belong to this property.');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('revoke_ical_token' . $token->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $token->revoke();
        $entityManager->flush();

        $this->addFlash('success', 'Token iCal révoqué avec succès.');

        return $this->redirectToRoute('app_host_ical_tokens_list', ['propertyId' => $propertyId]);
    }

    /**
     * View the iCal feed URL.
     */
    #[Route('/{id}/url', name: 'app_host_ical_tokens_url', methods: ['GET'])]
    public function viewUrl(
        string $propertyId,
        PropertyICalToken $token,
        PropertyRepository $propertyRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->find($propertyId);
        if ($property === null) {
            throw $this->createNotFoundException('Property not found.');
        }

        // Verify ownership
        if ($property->getHost()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this property.');
        }

        if ($token->getProperty()->getId() !== $property->getId()) {
            throw $this->createAccessDeniedException('Token does not belong to this property.');
        }

        if ($token->isRevoked()) {
            throw $this->createAccessDeniedException('This token has been revoked.');
        }

        $icalUrl = $this->generateUrl('api_property_ical_export', [
            'id' => $property->getId(),
            'token' => $token->getToken(),
        ], method: 'absolute');

        return $this->render('front/host/ical_tokens/url.html.twig', [
            'property' => $property,
            'token' => $token,
            'icalUrl' => $icalUrl,
        ]);
    }
}
