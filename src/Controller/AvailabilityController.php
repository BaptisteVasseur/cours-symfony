<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AvailabilityException;
use App\Entity\AvailabilitySchedule;
use App\Entity\Property;
use App\Entity\PropertyICalSync;
use App\Message\ICalImportMessage;
use App\Repository\AvailabilityExceptionRepository;
use App\Repository\AvailabilityScheduleRepository;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/property/{id}/availability', name: 'host_availability_')]
#[IsGranted('ROLE_HOST')]
class AvailabilityController extends AbstractController
{
    public function __construct(
        private readonly PropertyRepository $propertyRepo,
        private readonly AvailabilityScheduleRepository $scheduleRepo,
        private readonly AvailabilityExceptionRepository $exceptionRepo,
        private readonly PropertyICalSyncRepository $syncRepo,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(string $id): Response
    {
        $property = $this->getPropertyOr404($id);

        return $this->render('host/availability/index.html.twig', [
            'property' => $property,
            'schedules' => $property->getAvailabilitySchedules(),
            'exceptions' => $property->getAvailabilityExceptions(),
            'icalSyncs' => $property->getICalSyncs(),
        ]);
    }

    #[Route('/schedule/new', name: 'schedule_new', methods: ['GET', 'POST'])]
    public function newSchedule(string $id, Request $request): Response
    {
        $property = $this->getPropertyOr404($id);
        $schedule = new AvailabilitySchedule();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydrateSchedule($schedule, $data);
            $schedule->setProperty($property);

            $this->em->persist($schedule);
            $this->em->flush();

            $this->addFlash('success', 'Plage de disponibilité ajoutée.');

            return $this->redirectToRoute('host_availability_index', ['id' => $id]);
        }

        return $this->render('host/availability/schedule_form.html.twig', [
            'property' => $property,
            'schedule' => $schedule,
        ]);
    }

    #[Route('/schedule/{sid}/edit', name: 'schedule_edit', methods: ['GET', 'POST'])]
    public function editSchedule(string $id, string $sid, Request $request): Response
    {
        $property = $this->getPropertyOr404($id);
        $schedule = $this->scheduleRepo->find($sid);

        if ($schedule === null || (string) $schedule->getProperty()->getId() !== $id) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $this->hydrateSchedule($schedule, $request->request->all());
            $this->em->flush();
            $this->addFlash('success', 'Plage de disponibilité mise à jour.');

            return $this->redirectToRoute('host_availability_index', ['id' => $id]);
        }

        return $this->render('host/availability/schedule_form.html.twig', [
            'property' => $property,
            'schedule' => $schedule,
        ]);
    }

    #[Route('/schedule/{sid}/delete', name: 'schedule_delete', methods: ['POST'])]
    public function deleteSchedule(string $id, string $sid, Request $request): Response
    {
        $this->getPropertyOr404($id);
        $schedule = $this->scheduleRepo->find($sid);

        if ($schedule === null || (string) $schedule->getProperty()->getId() !== $id) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_schedule_' . $sid, $request->request->get('_token'))) {
            $this->em->remove($schedule);
            $this->em->flush();
            $this->addFlash('success', 'Plage supprimée.');
        }

        return $this->redirectToRoute('host_availability_index', ['id' => $id]);
    }

    #[Route('/exception/new', name: 'exception_new', methods: ['GET', 'POST'])]
    public function newException(string $id, Request $request): Response
    {
        $property = $this->getPropertyOr404($id);

        if ($request->isMethod('POST')) {
            $dateStr = $request->request->get('date');
            $reason = $request->request->get('reason');

            if ($dateStr) {
                $existing = $this->exceptionRepo->findOneByPropertyAndDate(
                    $property,
                    new \DateTimeImmutable($dateStr)
                );

                if ($existing === null) {
                    $exception = new AvailabilityException();
                    $exception->setProperty($property);
                    $exception->setDate(new \DateTimeImmutable($dateStr));
                    $exception->setReason($reason ?: null);
                    $exception->setSource(AvailabilityException::SOURCE_MANUAL);
                    $this->em->persist($exception);
                    $this->em->flush();
                    $this->addFlash('success', 'Date bloquée.');
                } else {
                    $this->addFlash('warning', 'Cette date est déjà bloquée.');
                }
            }

            return $this->redirectToRoute('host_availability_index', ['id' => $id]);
        }

        return $this->render('host/availability/exception_form.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/exception/{eid}/delete', name: 'exception_delete', methods: ['POST'])]
    public function deleteException(string $id, string $eid, Request $request): Response
    {
        $this->getPropertyOr404($id);
        $exception = $this->exceptionRepo->find($eid);

        if ($exception === null || (string) $exception->getProperty()->getId() !== $id) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_exception_' . $eid, $request->request->get('_token'))) {
            $this->em->remove($exception);
            $this->em->flush();
            $this->addFlash('success', 'Date débloquée.');
        }

        return $this->redirectToRoute('host_availability_index', ['id' => $id]);
    }

    #[Route('/ical/sync/new', name: 'ical_sync_new', methods: ['GET', 'POST'])]
    public function newICalSync(string $id, Request $request): Response
    {
        $property = $this->getPropertyOr404($id);

        if ($request->isMethod('POST')) {
            $url = $request->request->get('iCalUrl', '');
            $providerName = $request->request->get('providerName', 'Externe');

            $existing = $this->syncRepo->findOneBy([
                'property' => $property,
                'iCalUrl' => $url,
            ]);

            if ($existing !== null) {
                $this->bus->dispatch(new ICalImportMessage((string) $existing->getId()));
                $this->addFlash('info', 'Ce calendrier existe déjà, une synchronisation a été lancée.');
            } else {
                $sync = new PropertyICalSync();
                $sync->setProperty($property);
                $sync->setICalUrl($url);
                $sync->setProviderName($providerName);
                $this->em->persist($sync);
                $this->em->flush();

                $this->bus->dispatch(new ICalImportMessage((string) $sync->getId()));
                $this->addFlash('success', 'Calendrier ajouté, synchronisation en cours.');
            }

            return $this->redirectToRoute('host_availability_index', ['id' => $id]);
        }

        return $this->render('host/ical/sync_form.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/ical/sync/{sid}/delete', name: 'ical_sync_delete', methods: ['POST'])]
    public function deleteICalSync(string $id, string $sid, Request $request): Response
    {
        $this->getPropertyOr404($id);
        $sync = $this->syncRepo->find($sid);

        if ($sync === null || (string) $sync->getProperty()->getId() !== $id) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_sync_' . $sid, $request->request->get('_token'))) {
            $this->em->remove($sync);
            $this->em->flush();
            $this->addFlash('success', 'Calendrier externe supprimé.');
        }

        return $this->redirectToRoute('host_availability_index', ['id' => $id]);
    }

    #[Route('/ical/token/regenerate', name: 'ical_token_regenerate', methods: ['POST'])]
    public function regenerateIcalToken(string $id, Request $request): Response
    {
        $property = $this->getPropertyOr404($id);

        if ($this->isCsrfTokenValid('regenerate_ical_token_' . $id, $request->request->get('_token'))) {
            $property->regenerateIcalToken();
            $this->em->flush();
            $this->addFlash('success', 'Nouveau lien iCal généré. Mettez à jour vos abonnements sur les plateformes tierces.');
        }

        return $this->redirectToRoute('host_availability_index', ['id' => $id]);
    }

    private function getPropertyOr404(string $id): Property
    {
        $property = $this->propertyRepo->find($id);

        if ($property === null) {
            throw $this->createNotFoundException('Logement introuvable.');
        }

        if ((string) $property->getHost()->getId() !== (string) $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $property;
    }

    private function hydrateSchedule(AvailabilitySchedule $schedule, array $data): void
    {
        if (!empty($data['startDate'])) {
            $schedule->setStartDate(new \DateTimeImmutable($data['startDate']));
        }
        if (!empty($data['endDate'])) {
            $schedule->setEndDate(new \DateTimeImmutable($data['endDate']));
        }
        if (!empty($data['checkInTime'])) {
            $schedule->setCheckInTime(new \DateTimeImmutable($data['checkInTime']));
        }
        if (!empty($data['checkOutTime'])) {
            $schedule->setCheckOutTime(new \DateTimeImmutable($data['checkOutTime']));
        }

        $daysOfWeek = isset($data['daysOfWeek']) && is_array($data['daysOfWeek'])
            ? array_map('intval', $data['daysOfWeek'])
            : null;
        $schedule->setDaysOfWeek($daysOfWeek);

        $schedule->setMinimumStay((int) ($data['minimumStay'] ?? 1));
        $schedule->setMaximumStay(!empty($data['maximumStay']) ? (int) $data['maximumStay'] : null);
    }
}
