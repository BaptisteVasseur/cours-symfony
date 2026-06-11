<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Form\BlockDatesType;
use App\Repository\PropertyRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Security\Voter\PropertyVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
final class HostCalendarController extends AbstractController
{
    private const TOKEN_LENGTH = 32;

    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/proprietes/{id}/calendrier', name: 'app_host_calendar', methods: ['GET'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function index(
        Request $request,
        Property $property,
        PropertyRepository $propertyRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        $now = new \DateTimeImmutable();
        $year = (int) $request->query->get('year', (int) $now->format('Y'));
        $month = (int) $request->query->get('month', (int) $now->format('m'));

        // Build month navigation
        $currentMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $prevMonth = $currentMonth->modify('-1 month');
        $nextMonth = $currentMonth->modify('+1 month');

        $unavailableDates = $this->availabilityService->getUnavailableDates($property, $year, $month);

        $blockForm = $this->createForm(BlockDatesType::class, null, ['action' => $this->generateUrl('app_host_calendar_block', ['id' => $property->getId()])]);
        $unblockForm = $this->createForm(BlockDatesType::class, null, ['action' => $this->generateUrl('app_host_calendar_unblock', ['id' => $property->getId()])]);

        // Build the calendar URL for the host
        $calendarUrl = null;
        if ($property->getCalendarToken()) {
            $calendarUrl = $this->generateUrl('app_api_ical_export', [
                'id' => $property->getId(),
                'token' => $property->getCalendarToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $this->render('front/host/calendar.html.twig', [
            'property' => $property,
            'year' => $year,
            'month' => $month,
            'monthName' => $this->getMonthName($month),
            'unavailableDates' => $unavailableDates,
            'blockForm' => $blockForm,
            'unblockForm' => $unblockForm,
            'calendarUrl' => $calendarUrl,
            'prevMonth' => ['year' => (int) $prevMonth->format('Y'), 'month' => (int) $prevMonth->format('n')],
            'nextMonth' => ['year' => (int) $nextMonth->format('Y'), 'month' => (int) $nextMonth->format('n')],
        ]);
    }

    #[Route('/proprietes/{id}/calendrier/bloquer', name: 'app_host_calendar_block', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function block(Request $request, Property $property): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(BlockDatesType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $from = $data['fromDate'];
            $to = $data['toDate'];

            if ($from >= $to) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
            }

            $this->availabilityService->blockDates($property, $from, $to, $user);
            $this->addFlash('success', 'Les dates ont été bloquées.');
        }

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/proprietes/{id}/calendrier/debloquer', name: 'app_host_calendar_unblock', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function unblock(Request $request, Property $property): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(BlockDatesType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $from = $data['fromDate'];
            $to = $data['toDate'];

            if ($from >= $to) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
            }

            $this->availabilityService->unblockDates($property, $from, $to);
            $this->addFlash('success', 'Les dates ont été débloquées.');
        }

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/proprietes/{id}/token', name: 'app_host_calendar_token', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function generateToken(Property $property, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('generate_token' . $property->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $property->setCalendarToken(bin2hex(random_bytes(self::TOKEN_LENGTH)));
        $this->em->flush();

        $url = $this->generateUrl('app_api_ical_export', [
            'id' => $property->getId(),
            'token' => $property->getCalendarToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->addFlash('success', 'Token généré. URL du calendrier : ' . $url);

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    #[Route('/proprietes/{id}/token/revoke', name: 'app_host_calendar_token_revoke', methods: ['POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function revokeToken(Property $property, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('revoke_token' . $property->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $property->setCalendarToken(null);
        $this->em->flush();

        $this->addFlash('success', 'Token révoqué. L\'ancien lien de calendrier n\'est plus valide.');

        return $this->redirectToRoute('app_host_calendar', ['id' => $property->getId()]);
    }

    private function getMonthName(int $month): string
    {
        $names = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return $names[$month] ?? (string) $month;
    }
}
