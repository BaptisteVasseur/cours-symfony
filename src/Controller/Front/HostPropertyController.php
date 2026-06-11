<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Service\PropertyCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\PropertyVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes')]
#[IsGranted('ROLE_USER')]
final class HostPropertyController extends AbstractController
{
    #[Route('/{id}/calendrier', name: 'app_host_property_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function calendar(
        Property $property,
        Request $request,
        PropertyRepository $propertyRepository,
        PropertyCalendarService $propertyCalendarService,
    ): Response {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        $month = $request->query->get('month');
        $currentMonth = null;
        if ($month !== null && $month !== '') {
            $parsedMonth = \DateTimeImmutable::createFromFormat('Y-m', $month);
            if ($parsedMonth !== false) {
                $currentMonth = $parsedMonth->setTime(0, 0);
            }
        }
        $currentMonth ??= new \DateTimeImmutable('first day of this month');

        return $this->render('front/account/property_calendar.html.twig', [
            'property' => $property,
            'currentMonth' => $currentMonth,
            'previousMonth' => $currentMonth->modify('-1 month'),
            'nextMonth' => $currentMonth->modify('+1 month'),
            'weeks' => $propertyCalendarService->buildMonth($property, $currentMonth),
        ]);
    }

    #[Route('/{id}/calendrier', name: 'app_host_property_calendar_update', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function updateCalendar(
        Property $property,
        Request $request,
        PropertyCalendarService $propertyCalendarService,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('host_property_calendar_' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $startDate = $this->parseDate((string) $request->request->get('startDate'));
        $endDate = $this->parseDate((string) $request->request->get('endDate'));

        if ($startDate === null || $endDate === null) {
            $this->addFlash('error', 'Les dates saisies sont invalides.');

            return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()]);
        }

        $availabilityMode = (string) $request->request->get('availabilityMode', 'blocked');
        $minimumStay = $request->request->get('minimumStay');
        $priceOverride = trim((string) $request->request->get('priceOverride'));
        $blockedReason = trim((string) $request->request->get('blockedReason'));

        try {
            $propertyCalendarService->updatePeriod(
                $property,
                $startDate,
                $endDate,
                $availabilityMode === 'available',
                $blockedReason !== '' ? $blockedReason : null,
                $minimumStay !== null && $minimumStay !== '' ? (int) $minimumStay : null,
                $priceOverride !== '' ? number_format((float) str_replace(',', '.', $priceOverride), 2, '.', '') : null,
            );
            $entityManager->flush();
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()]);
        }

        $this->addFlash('success', $availabilityMode === 'available'
            ? 'Le calendrier a été rouvert sur la période sélectionnée.'
            : 'La période a bien été mise à jour dans le calendrier.');

        return $this->redirectToRoute('app_host_property_calendar', [
            'id' => $property->getId(),
            'month' => $startDate->format('Y-m'),
        ]);
    }

    #[Route('/{id}/calendar-token', name: 'app_host_property_refresh_calendar_token', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function refreshCalendarToken(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('host_property_calendar_token_' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $property->refreshCalendarExportToken();
        $entityManager->flush();

        $this->addFlash('success', 'Le lien iCal a été régénéré.');

        return $this->redirectToRoute('app_host_property_calendar', ['id' => $property->getId()]);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
