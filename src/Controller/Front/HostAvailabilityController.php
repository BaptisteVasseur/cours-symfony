<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Form\BlockedPeriodType;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/logement/{id}/disponibilites', name: 'app_host_availability_')]
#[IsGranted('ROLE_USER')]
final class HostAvailabilityController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
        PropertyAvailabilityRepository $availabilityRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $this->denyAccessUnlessOwner($property);

        $block = new PropertyAvailability();
        $block->setProperty($property);

        $form = $this->createForm(BlockedPeriodType::class, $block);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($block->getStartDate() >= $block->getEndDate()) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
            } else {
                $entityManager->persist($block);
                $entityManager->flush();
                $this->addFlash('success', 'Période bloquée enregistrée.');

                return $this->redirectToRoute('app_host_availability_index', ['id' => $property->getId()]);
            }
        }

        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        if ($month < 1) {
            $month = 12;
            --$year;
        } elseif ($month > 12) {
            $month = 1;
            ++$year;
        }

        $blocks = $availabilityRepository->findByPropertyOrdered($property);
        $reservations = $reservationRepository->findByPropertyForCalendar($property);
        $calendarDays = $this->buildCalendar($year, $month, $blocks, $reservations);

        return $this->render('front/host/availability/index.html.twig', [
            'property' => $property,
            'form' => $form,
            'calendarDays' => $calendarDays,
            'year' => $year,
            'month' => $month,
            'monthLabel' => \DateTimeImmutable::createFromFormat('Y-n', "$year-$month")->format('F Y'),
            'blocks' => $blocks,
        ]);
    }

    #[Route('/supprimer/{blockId}', name: 'delete', methods: ['POST'])]
    public function delete(
        Property $property,
        string $blockId,
        Request $request,
        EntityManagerInterface $entityManager,
        PropertyAvailabilityRepository $availabilityRepository,
    ): Response {
        $this->denyAccessUnlessOwner($property);

        $block = $availabilityRepository->find($blockId);

        if ($block === null || $block->getProperty()?->getId() !== $property->getId()) {
            throw $this->createNotFoundException('Période introuvable.');
        }

        if ($this->isCsrfTokenValid('delete_block_'.$blockId, $request->request->get('_token'))) {
            $entityManager->remove($block);
            $entityManager->flush();
            $this->addFlash('success', 'Période supprimée.');
        }

        return $this->redirectToRoute('app_host_availability_index', ['id' => $property->getId()]);
    }

    private function denyAccessUnlessOwner(Property $property): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || $property->getHost()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas l\'hôte de ce logement.');
        }
    }

    /**
     * @param list<\App\Entity\PropertyAvailability> $blocks
     * @param list<\App\Entity\Reservation>          $reservations
     *
     * @return array<string, string> date ISO => status ('blocked'|'confirmed'|'pending'|'free')
     */
    private function buildCalendar(int $year, int $month, array $blocks, array $reservations): array
    {
        $days = [];
        $daysInMonth = (int) (new \DateTimeImmutable("$year-$month-01"))->format('t');

        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $date = \DateTimeImmutable::createFromFormat('Y-n-j', "$year-$month-$d");
            $iso = $date->format('Y-m-d');
            $days[$iso] = 'free';
        }

        foreach ($blocks as $block) {
            $cursor = $block->getStartDate();
            while ($cursor < $block->getEndDate()) {
                $iso = $cursor->format('Y-m-d');
                if (isset($days[$iso])) {
                    $days[$iso] = 'blocked';
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        foreach ($reservations as $reservation) {
            $cursor = $reservation->getCheckinDate();
            while ($cursor < $reservation->getCheckoutDate()) {
                $iso = $cursor->format('Y-m-d');
                if (isset($days[$iso])) {
                    $status = $reservation->getStatus();
                    if ($days[$iso] !== 'confirmed') {
                        $days[$iso] = $status;
                    }
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $days;
    }
}
