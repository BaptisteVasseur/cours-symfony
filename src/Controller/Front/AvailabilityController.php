<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Form\BlockedPeriodType;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/hote/logement/{id}/disponibilites', name: 'app_availability_')]
final class AvailabilityController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Property $property,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $this->denyAccessUnlessGranted('HOST', $property);

        $blockedDates = $availabilityRepository->findBlockedDates($property);

        return $this->render('front/availability/index.html.twig', [
            'property'     => $property,
            'blockedDates' => $blockedDates,
        ]);
    }

    #[Route('/bloquer', name: 'block', methods: ['GET', 'POST'])]
    public function block(
        Property $property,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('HOST', $property);

        $form = $this->createForm(BlockedPeriodType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data      = $form->getData();
            $dateStart = \DateTimeImmutable::createFromInterface($data['dateStart']);
            $dateEnd   = \DateTimeImmutable::createFromInterface($data['dateEnd']);
            $reason    = $data['reason'] ?? null;

            if ($dateEnd <= $dateStart) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                return $this->redirectToRoute('app_availability_block', ['id' => $property->getId()]);
            }

            $existing = $availabilityRepository->findBlockedInRange($property, $dateStart, $dateEnd);
            $existingDates = array_map(
                fn(PropertyAvailability $a) => $a->getAvailableDate()->format('Y-m-d'),
                $existing
            );

            $current = $dateStart;
            while ($current <= $dateEnd) {
                $dateStr = $current->format('Y-m-d');
                if (!in_array($dateStr, $existingDates, true)) {
                    $availability = new PropertyAvailability();
                    $availability->setProperty($property);
                    $availability->setAvailableDate($current);
                    $availability->setIsAvailable(false);
                    if ($reason) {
                        $availability->setReason($reason);
                    }
                    $entityManager->persist($availability);
                }
                $current = $current->modify('+1 day');
            }


            $entityManager->flush();
            $this->addFlash('success', 'Période bloquée avec succès.');

            return $this->redirectToRoute('app_availability_index', ['id' => $property->getId()]);
        }

        return $this->render('front/availability/block.html.twig', [
            'property' => $property,
            'form'     => $form,
        ]);
    }

    #[Route('/debloquer/{availabilityId}', name: 'unblock', methods: ['POST'])]
    public function unblock(
        Property $property,
        string $availabilityId,
        Request $request,
        PropertyAvailabilityRepository $availabilityRepository,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $this->denyAccessUnlessGranted('HOST', $property);

        $token = new CsrfToken('unblock' . $availabilityId, $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $availability = $availabilityRepository->find($availabilityId);
        if ($availability && $availability->getProperty() === $property) {
            $entityManager->remove($availability);
            $entityManager->flush();
            $this->addFlash('success', 'Date débloquée.');
        }

        return $this->redirectToRoute('app_availability_index', ['id' => $property->getId()]);
    }

    #[Route('/generer-token-ical', name: 'generate_ical_token', methods: ['POST'])]
    public function generateIcalToken(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $this->denyAccessUnlessGranted('HOST', $property);

        $token = new CsrfToken('generate_ical_token', $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $property->generateIcalToken();
        $entityManager->flush();

        $icalUrl = $this->generateUrl('api_property_ical', [
            'id'    => $property->getId(),
            'token' => $property->getIcalToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'token'   => $property->getIcalToken(),
                'icalUrl' => $icalUrl,
            ]);
        }

        $this->addFlash('success', 'Lien iCal généré. Vous pouvez le copier ci-dessous.');

        return $this->redirectToRoute('app_availability_index', ['id' => $property->getId()]);
    }
}
