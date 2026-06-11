<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Form\PropertyBlockType;
use App\Repository\PropertyAvailabilityRepository;
use App\Security\Voter\PropertyVoter;
use App\Service\HostCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes/{id}/calendrier')]
#[IsGranted('ROLE_USER')]
#[IsGranted(PropertyVoter::EDIT, subject: 'property')]
final class HostCalendarController extends AbstractController
{
    #[Route('', name: 'app_host_calendar', methods: ['GET'])]
    public function index(Property $property, Request $request, HostCalendarService $calendarService): Response
    {
        [$year, $month] = $this->resolveMonth($request->query->getString('mois'));

        $block = new PropertyAvailability();
        $block->setProperty($property);
        $block->setIsAvailable(false);
        $form = $this->createForm(PropertyBlockType::class, $block, [
            'action' => $this->generateUrl('app_host_calendar_block', ['id' => $property->getId()]),
        ]);

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'calendar' => $calendarService->buildMonth($property, $year, $month),
            'blockForm' => $form,
        ]);
    }

    #[Route('/blocages', name: 'app_host_calendar_block', methods: ['POST'])]
    public function block(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $block = new PropertyAvailability();
        $block->setProperty($property);
        $block->setIsAvailable(false);

        $form = $this->createForm(PropertyBlockType::class, $block);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $conflict = $availabilityRepository->findOverlappingBlock(
                $property,
                $block->getStartDate(),
                $block->getEndDate(),
            );

            if ($conflict !== null) {
                $this->addFlash('error', 'Un blocage existe déjà sur cette période.');
            } else {
                $entityManager->persist($block);
                $entityManager->flush();
                $this->addFlash('success', 'Période d\'indisponibilité enregistrée.');
            }
        } else {
            $this->addFlash('error', 'Le blocage n\'a pas pu être enregistré, vérifiez les dates.');
        }

        return $this->redirectToCalendar($property, $block->getStartDate());
    }

    #[Route('/blocages/{blockId}/supprimer', name: 'app_host_calendar_block_delete', methods: ['POST'])]
    public function deleteBlock(
        Property $property,
        #[MapEntity(id: 'blockId')] PropertyAvailability $block,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($block->getProperty() !== $property) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete-block-' . $block->getId(), $request->request->getString('_token'))) {
            $start = $block->getStartDate();
            $entityManager->remove($block);
            $entityManager->flush();
            $this->addFlash('success', 'Blocage supprimé.');

            return $this->redirectToCalendar($property, $start);
        }

        return $this->redirectToCalendar($property, null);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveMonth(string $monthParam): array
    {
        $reference = new \DateTimeImmutable('today');

        if (preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $monthParam . '-01');
            if ($parsed instanceof \DateTimeImmutable) {
                $reference = $parsed;
            }
        }

        return [(int) $reference->format('Y'), (int) $reference->format('n')];
    }

    private function redirectToCalendar(Property $property, ?\DateTimeImmutable $month): Response
    {
        $params = ['id' => $property->getId()];
        if ($month !== null) {
            $params['mois'] = $month->format('Y-m');
        }

        return $this->redirectToRoute('app_host_calendar', $params);
    }
}
