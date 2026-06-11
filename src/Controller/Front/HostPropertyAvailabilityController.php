<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Exception\PropertyAvailabilityException;
use App\Form\PropertyAvailabilityRangeType;
use App\Security\Voter\PropertyVoter;
use App\Service\Booking\PropertyAvailabilityCalendarBuilder;
use App\Service\Booking\PropertyAvailabilityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes')]
#[IsGranted('ROLE_HOST')]
final class HostPropertyAvailabilityController extends AbstractController
{
    #[Route('/{id}/disponibilites', name: 'app_host_property_availability_show', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function show(
        Request $request,
        Property $property,
        PropertyAvailabilityCalendarBuilder $calendarBuilder,
        PropertyAvailabilityManager $propertyAvailabilityManager,
        EntityManagerInterface $entityManager,
    ): Response {
        $displayedMonth = $this->resolveDisplayedMonth($request->query->getString('month'));

        if ($property->getICalExportToken() === null) {
            $property->regenerateICalExportToken();
            $entityManager->flush();
        }

        $form = $this->createForm(PropertyAvailabilityRangeType::class, [
            'startDate' => $displayedMonth,
            'endDate' => $displayedMonth,
            'mode' => 'blocked',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $priceOverride = $data['priceOverride'] ?? null;
            $minimumStay = $data['minimumStay'] ?? null;

            try {
                $propertyAvailabilityManager->applyRange(
                    $property,
                    $data['startDate'],
                    $data['endDate'],
                    $data['mode'],
                    $minimumStay !== null && $minimumStay !== '' ? (int) $minimumStay : null,
                    $priceOverride !== null && $priceOverride !== '' ? (float) $priceOverride : null,
                );

                $this->addFlash('success', 'Le calendrier a ete mis a jour.');

                return $this->redirectToRoute('app_host_property_availability_show', [
                    'id' => $property->getId(),
                    'month' => $displayedMonth->format('Y-m'),
                ]);
            } catch (PropertyAvailabilityException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('front/property_availability/show.html.twig', [
            'property' => $property,
            'form' => $form,
            'icalExportUrl' => $this->generateUrl('app_property_ical_export', [
                'token' => $property->getICalExportToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            ...$calendarBuilder->build($property, $displayedMonth),
        ]);
    }

    #[Route('/{id}/disponibilites/ical/regenerate', name: 'app_host_property_ical_regenerate', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function regenerateICal(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('property_ical_regenerate_' . $property->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $property->regenerateICalExportToken();
        $property->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Le lien iCal a ete regenere.');

        return $this->redirectToRoute('app_host_property_availability_show', [
            'id' => $property->getId(),
            'month' => $this->resolveDisplayedMonth($request->request->getString('month'))->format('Y-m'),
        ]);
    }

    private function resolveDisplayedMonth(string $value): \DateTimeImmutable
    {
        if ($value !== '') {
            $parsedMonth = \DateTimeImmutable::createFromFormat('!Y-m', $value);
            if ($parsedMonth instanceof \DateTimeImmutable) {
                return $parsedMonth;
            }
        }

        return new \DateTimeImmutable('first day of this month');
    }
}
