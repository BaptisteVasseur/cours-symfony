<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\AvailabilityBlock;
use App\Entity\Property;
use App\Entity\User;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_HOST')]
final class CalendarController extends AbstractController
{
    #[Route('/host/property/{id}/calendar', name: 'app_host_calendar', methods: ['GET'])]
    #[Route('/compte/hote/logements/{id}/calendrier', name: 'app_host_calendar_legacy', methods: ['GET'])]
    public function index(Request $request, Property $property, AvailabilityService $availabilityService): Response
    {
        $this->denyUnlessOwner($property);

        $month = $this->parseMonth($request->query->get('month')) ?? new \DateTimeImmutable('first day of this month');

        return $this->render('front/host_calendar/index.html.twig', [
            'property' => $property,
            'calendar' => $availabilityService->getMonthlyCalendar($property, $month),
        ]);
    }

    #[Route('/host/property/{id}/block', name: 'app_host_block_create', methods: ['POST'])]
    #[Route('/compte/hote/logements/{id}/calendrier/blocages', name: 'app_host_block_create_legacy', methods: ['POST'])]
    public function createBlock(Request $request, Property $property, AvailabilityService $availabilityService): Response
    {
        $this->denyUnlessOwner($property);

        if (!$this->isCsrfTokenValid('block_period'.$property->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $start = $this->parseDate($request->request->get('start_date'));
        $end = $this->parseDate($request->request->get('end_date'));
        if ($start === null || $end === null) {
            $this->addFlash('error', 'Les dates de blocage sont invalides.');
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        try {
            $availabilityService->blockPeriod($property, $start, $end, (string) $request->request->get('notes'));
            $this->addFlash('success', 'La période a été bloquée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'month' => $start->format('Y-m'),
        ]);
    }

    #[Route('/host/block/{blockId}', name: 'app_host_block_delete', methods: ['POST'])]
    #[Route('/compte/hote/blocages/{id}/supprimer', name: 'app_host_block_delete_legacy', methods: ['POST'])]
    public function deleteBlock(
        Request $request,
        AvailabilityService $availabilityService,
        EntityManagerInterface $entityManager,
        ?string $blockId = null,
        ?string $id = null,
    ): Response {
        $actualId = $blockId ?? $id;
        if ($actualId === null) {
            throw $this->createNotFoundException();
        }

        $block = $entityManager->getRepository(AvailabilityBlock::class)->find($actualId);
        if (!$block) {
            throw $this->createNotFoundException();
        }

        $property = $block->getProperty();
        if ($property === null) {
            throw $this->createNotFoundException();
        }

        $this->denyUnlessOwner($property);

        if (!$this->isCsrfTokenValid('delete_block'.$block->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
        }

        $month = $block->getStartDate()?->format('Y-m');
        $availabilityService->removeBlock($block);
        $this->addFlash('success', 'La période est à nouveau disponible.');

        return $this->redirectToRoute('app_host_calendar', [
            'id' => $property->getId(),
            'month' => $month,
        ]);
    }

    private function denyUnlessOwner(Property $property): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function parseMonth(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value.'-01');

        return $date !== false ? $date : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
