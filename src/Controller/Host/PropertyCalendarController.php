<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Service\CalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/properties')]
#[IsGranted('ROLE_HOST')]
final class PropertyCalendarController extends AbstractController
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Liste des logements de l'hôte connecté, avec accès à leur calendrier.
     */
    #[Route('', name: 'app_host_property_index', methods: ['GET'])]
    public function index(PropertyRepository $propertyRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('host/property/index.html.twig', [
            'properties' => $propertyRepository->findBy(['host' => $user], ['createdAt' => 'DESC']),
        ]);
    }

    /**
     * Vue mensuelle du calendrier d'un logement (?month=YYYY-MM).
     */
    #[Route('/{id}/calendar', name: 'app_host_property_calendar', methods: ['GET'])]
    public function calendar(Property $property, Request $request): Response
    {
        $this->denyUnlessOwner($property);

        [$year, $month] = $this->resolveMonth($request->query->get('month'));
        $current = (new \DateTimeImmutable())->setDate($year, $month, 1);

        return $this->render('host/property/calendar.html.twig', [
            'property' => $property,
            'calendar' => $this->calendarService->buildMonthView($property, $year, $month),
            'prevMonth' => $current->modify('-1 month')->format('Y-m'),
            'nextMonth' => $current->modify('+1 month')->format('Y-m'),
        ]);
    }

    #[Route('/{id}/calendar/block', name: 'app_host_property_block', methods: ['POST'])]
    public function block(Property $property, Request $request): Response
    {
        $this->denyUnlessOwner($property);
        $this->applyPeriodAction($property, $request, block: true);

        return $this->redirectToCalendar($property, $request);
    }

    #[Route('/{id}/calendar/unblock', name: 'app_host_property_unblock', methods: ['POST'])]
    public function unblock(Property $property, Request $request): Response
    {
        $this->denyUnlessOwner($property);
        $this->applyPeriodAction($property, $request, block: false);

        return $this->redirectToCalendar($property, $request);
    }

    #[Route('/{id}/ical/generate', name: 'app_host_property_ical_generate', methods: ['POST'])]
    public function generateIcalToken(Property $property, Request $request): Response
    {
        $this->denyUnlessOwner($property);
        if (!$this->isCsrfTokenValid('ical' . $property->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToCalendar($property, $request);
        }

        $property->setIcalToken(bin2hex(random_bytes(20)));
        $this->em->flush();
        $this->addFlash('success', 'Lien iCal généré.');

        return $this->redirectToCalendar($property, $request);
    }

    #[Route('/{id}/ical/revoke', name: 'app_host_property_ical_revoke', methods: ['POST'])]
    public function revokeIcalToken(Property $property, Request $request): Response
    {
        $this->denyUnlessOwner($property);
        if (!$this->isCsrfTokenValid('ical' . $property->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToCalendar($property, $request);
        }

        $property->setIcalToken(null);
        $this->em->flush();
        $this->addFlash('success', 'Lien iCal révoqué.');

        return $this->redirectToCalendar($property, $request);
    }

    private function applyPeriodAction(Property $property, Request $request, bool $block): void
    {
        if (!$this->isCsrfTokenValid('calendar' . $property->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return;
        }

        try {
            $from = new \DateTimeImmutable((string) $request->request->get('start'));
            $to = new \DateTimeImmutable((string) $request->request->get('end'));
        } catch (\Exception) {
            $this->addFlash('error', 'Dates invalides.');

            return;
        }

        if ($block) {
            $this->calendarService->blockPeriod($property, $from, $to);
            $this->addFlash('success', 'Période bloquée.');
        } else {
            $this->calendarService->unblockPeriod($property, $from, $to);
            $this->addFlash('success', 'Période débloquée.');
        }
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function resolveMonth(?string $value): array
    {
        if ($value !== null && preg_match('/^(\d{4})-(\d{1,2})$/', $value, $m) === 1) {
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return [(int) $m[1], $month];
            }
        }

        $now = new \DateTimeImmutable();

        return [(int) $now->format('Y'), (int) $now->format('n')];
    }

    private function redirectToCalendar(Property $property, Request $request): Response
    {
        $params = ['id' => $property->getId()];
        $month = $request->query->get('month');
        if ($month !== null && $month !== '') {
            $params['month'] = $month;
        }

        return $this->redirectToRoute('app_host_property_calendar', $params);
    }

    private function denyUnlessOwner(Property $property): void
    {
        if ($property->getHost() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce logement ne vous appartient pas.');
        }
    }
}
