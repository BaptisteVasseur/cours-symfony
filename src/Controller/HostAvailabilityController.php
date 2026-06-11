<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AvailabilityBlock;
use App\Entity\Listing;
use App\Security\Voter\ListingVoter;
use App\Service\CalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_HOST')]
final class HostAvailabilityController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/listings/{id}/calendar', name: 'app_host_calendar', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function calendar(Listing $listing, Request $request, CalendarService $calendarService): Response
    {
        $this->denyAccessUnlessGranted(ListingVoter::MANAGE, $listing);

        $now = new \DateTimeImmutable();
        $year = (int) $request->query->get('year', $now->format('Y'));
        $month = (int) $request->query->get('month', $now->format('n'));
        if ($month < 1 || $month > 12) {
            $year = (int) $now->format('Y');
            $month = (int) $now->format('n');
        }

        return $this->render('host/calendar.html.twig', [
            'listing' => $listing,
            'calendar' => $calendarService->buildMonth($listing, $year, $month),
            'blocks' => $listing->getAvailabilityBlocks(),
        ]);
    }

    #[Route('/listings/{id}/blocks', name: 'app_host_block_create', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function createBlock(Listing $listing, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ListingVoter::MANAGE, $listing);
        $this->assertCsrf($request, 'block_' . $listing->getId());

        $start = $this->parseDate($request->request->get('startDate'));
        $end = $this->parseDate($request->request->get('endDate'));
        $reason = trim((string) $request->request->get('reason')) ?: null;

        if ($start === null || $end === null || $end <= $start) {
            $this->addFlash('error', 'Période invalide : la date de fin doit être postérieure à la date de début.');

            return $this->redirectToCalendar($listing);
        }

        $block = (new AvailabilityBlock())
            ->setListing($listing)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setReason($reason)
            ->setSource(AvailabilityBlock::SOURCE_MANUAL);

        $this->em->persist($block);
        $this->em->flush();

        $this->addFlash('success', 'Période d\'indisponibilité ajoutée.');

        return $this->redirectToCalendar($listing);
    }

    #[Route('/blocks/{id}/delete', name: 'app_host_block_delete', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function deleteBlock(AvailabilityBlock $block, Request $request): Response
    {
        $listing = $block->getListing();
        $this->denyAccessUnlessGranted(ListingVoter::MANAGE, $listing);
        $this->assertCsrf($request, 'block_delete_' . $block->getId());

        $this->em->remove($block);
        $this->em->flush();

        $this->addFlash('success', 'Indisponibilité supprimée.');

        return $this->redirectToCalendar($listing);
    }

    #[Route('/listings/{id}/calendar-token', name: 'app_host_calendar_token', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function regenerateToken(Listing $listing, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ListingVoter::MANAGE, $listing);
        $this->assertCsrf($request, 'token_' . $listing->getId());

        $listing->regenerateCalendarToken();
        $this->em->flush();

        $this->addFlash('success', 'Jeton iCal régénéré : l\'ancien lien de synchronisation est désormais révoqué.');

        return $this->redirectToCalendar($listing);
    }

    private function redirectToCalendar(Listing $listing): Response
    {
        return $this->redirectToRoute('app_host_calendar', ['id' => $listing->getId()]);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
