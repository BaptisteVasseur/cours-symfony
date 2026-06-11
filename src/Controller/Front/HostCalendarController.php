<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Blockout;
use App\Entity\Property;
use App\Entity\User;
use App\Form\BlockoutType;
use App\Form\PropertyAvailabilitySettingsType;
use App\Repository\BlockoutRepository;
use App\Repository\ReservationRepository;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes')]
#[IsGranted('ROLE_HOST')]
final class HostCalendarController extends AbstractController
{
    #[Route('/{id}/calendrier', name: 'app_host_property_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function calendar(
        Property $property,
        Request $request,
        ReservationRepository $reservationRepo,
        BlockoutRepository $blockoutRepo,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $today = new \DateTimeImmutable('today');
        $year  = (int) $request->query->get('year', $today->format('Y'));
        $month = (int) $request->query->get('month', $today->format('n'));

        if ($month < 1) {
            $month = 12;
            --$year;
        } elseif ($month > 12) {
            $month = 1;
            ++$year;
        }

        $firstDay = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay  = $firstDay->modify('last day of this month');

        $reservations = $reservationRepo->findForPropertyInRange($property, $firstDay, $lastDay);
        $blockouts     = $blockoutRepo->findForPropertyInRange($property, $firstDay, $lastDay);

        $calendarMap = [];
        foreach ($reservations as $reservation) {
            $cursor = $reservation->getCheckinDate();
            $end    = $reservation->getCheckoutDate();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $calendarMap[$key]['reservations'][] = $reservation;
                $cursor = $cursor->modify('+1 day');
            }
        }
        foreach ($blockouts as $blockout) {
            $cursor = $blockout->getStartDate();
            $end    = $blockout->getEndDate();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $calendarMap[$key]['blockouts'][] = $blockout;
                $cursor = $cursor->modify('+1 day');
            }
        }

        $blockout = new Blockout();
        $blockout->setProperty($property);
        $blockout->setCreatedBy($user);
        $form = $this->createForm(BlockoutType::class, $blockout, [
            'action' => $this->generateUrl('app_host_blockout_add', ['id' => $property->getId()]),
        ]);

        $prevMonth = $firstDay->modify('-1 month');
        $nextMonth = $firstDay->modify('+1 month');

        return $this->render('front/host/calendar.html.twig', [
            'property'       => $property,
            'year'           => $year,
            'month'          => $month,
            'firstDay'       => $firstDay,
            'lastDay'        => $lastDay,
            'today'          => $today,
            'calendarMap'    => $calendarMap,
            'reservations'   => $reservations,
            'blockouts'      => $blockoutRepo->findByPropertyOrderedByDate($property),
            'form'           => $form,
            'prevMonth'      => $prevMonth,
            'nextMonth'      => $nextMonth,
        ]);
    }

    #[Route('/{id}/blockout/ajouter', name: 'app_host_blockout_add', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function blockoutAdd(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $blockout = new Blockout();
        $blockout->setProperty($property);
        $blockout->setCreatedBy($user);

        $form = $this->createForm(BlockoutType::class, $blockout);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($blockout);
            $entityManager->flush();
            $this->addFlash('success', 'Période d\'indisponibilité ajoutée.');
        } else {
            $this->addFlash('error', 'Formulaire invalide. Vérifiez les dates.');
        }

        return $this->redirectToRoute('app_host_property_calendar', [
            'id'    => $property->getId(),
            'year'  => $request->request->get('year', (new \DateTimeImmutable())->format('Y')),
            'month' => $request->request->get('month', (new \DateTimeImmutable())->format('n')),
        ]);
    }

    #[Route('/blockout/{blockoutId}/supprimer', name: 'app_host_blockout_delete', methods: ['POST'])]
    public function blockoutDelete(
        string $blockoutId,
        Request $request,
        EntityManagerInterface $entityManager,
        BlockoutRepository $blockoutRepo,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $blockout = $blockoutRepo->find($blockoutId);
        if (!$blockout) {
            throw $this->createNotFoundException('Période d\'indisponibilité introuvable.');
        }

        $property = $blockout->getProperty();
        if ($property?->getHost()?->getId() !== $user->getId()
            && !in_array('ROLE_ADMIN', $user->getRoles(), true)
        ) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_blockout_' . $blockout->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($blockout);
            $entityManager->flush();
            $this->addFlash('success', 'Période d\'indisponibilité supprimée.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_host_property_calendar', [
            'id'    => $property?->getId(),
            'year'  => $request->request->get('year', (new \DateTimeImmutable())->format('Y')),
            'month' => $request->request->get('month', (new \DateTimeImmutable())->format('n')),
        ]);
    }

    #[Route('/{id}/disponibilites/reglages', name: 'app_host_property_availability_settings', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function availabilitySettings(
        Property $property,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(PropertyAvailabilitySettingsType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Paramètres de disponibilité enregistrés.');

            return $this->redirectToRoute('app_host_property_availability_settings', ['id' => $property->getId()]);
        }

        return $this->render('front/host/availability_settings.html.twig', [
            'property' => $property,
            'form'     => $form,
        ]);
    }
}
