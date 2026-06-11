<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Intercepte les refus d'accès d'un utilisateur authentifié mais sans les droits
 * requis (ex. un voyageur qui tente d'accéder à l'espace hôte ou admin).
 *
 * - Requête navigateur : redirection vers l'accueil + message flash, plutôt qu'une
 *   page « Access Denied ».
 * - Requête API/JSON : on conserve un vrai 403 (les clients ne suivent pas de redirection).
 *
 * N.B. : ne concerne PAS les utilisateurs anonymes (redirigés vers la connexion
 * par le point d'entrée du firewall), ni l'export iCal qui lève un
 * AccessDeniedHttpException (403 conservé).
 */
final readonly class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $session = $request->getSession();
            if (method_exists($session, 'getFlashBag')) {
                $session->getFlashBag()->add('error', "Accès refusé : vous n'avez pas les droits nécessaires pour accéder à cette page.");
            }
        } catch (SessionNotFoundException) {
            // Pas de session disponible : on redirige sans message flash.
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    private function expectsJson(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api')
            || $request->isXmlHttpRequest()
            || in_array('application/json', $request->getAcceptableContentTypes(), true);
    }
}
