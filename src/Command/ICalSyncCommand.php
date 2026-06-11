<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PropertyBlockedPeriod;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyBlockedPeriodRepository;
use App\Repository\PropertyICalSyncRepository;
use App\Repository\ReservationRepository;
use App\Service\ICalParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Synchronise les calendriers iCal externes (Partie F). Conçue pour être
 * lancée par un cron (ex. toutes les heures).
 *
 * Stratégie de gestion des conflits :
 *  - chaque événement distant est identifié par son UID et tracé via
 *    PropertyBlockedPeriod.externalUid : nouvel UID = création, UID connu =
 *    mise à jour des dates, UID disparu du flux = suppression du blocage
 *    local (la suppression distante libère les nuitées) ;
 *  - les blocages manuels de l'hôte et les réservations ne sont JAMAIS
 *    modifiés par la synchronisation : seules les périodes issues du même
 *    flux (syncSource) sont réconciliées ;
 *  - chevauchement avec une réservation confirmée existante : le blocage est
 *    importé quand même (défensif : le créneau est occupé des deux côtés)
 *    et un avertissement est émis pour que l'hôte arbitre le double-booking.
 */
#[AsCommand(
    name: 'app:ical:sync',
    description: 'Importe les calendriers iCal externes et bloque les nuitées correspondantes',
)]
final class ICalSyncCommand extends Command
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepository,
        private readonly PropertyBlockedPeriodRepository $blockedPeriodRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly ICalParser $parser,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('property', null, InputOption::VALUE_REQUIRED, 'Ne synchroniser que ce logement (id)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $criteria = [];
        $propertyId = $input->getOption('property');
        if ($propertyId !== null) {
            $criteria['property'] = $propertyId;
        }

        $syncs = $this->syncRepository->findBy($criteria);
        if ($syncs === []) {
            $io->info('Aucun flux iCal à synchroniser.');

            return Command::SUCCESS;
        }

        $hasError = false;
        foreach ($syncs as $sync) {
            $hasError = !$this->syncOne($sync, $io) || $hasError;
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }

    private function syncOne(PropertyICalSync $sync, SymfonyStyle $io): bool
    {
        $property = $sync->getProperty();
        $io->section(sprintf('%s — %s', $property->getTitle(), $sync->getProviderName()));

        try {
            $response = $this->httpClient->request('GET', $sync->getICalUrl(), ['timeout' => 15]);
            $events = $this->parser->parse($response->getContent());
        } catch (\Throwable $e) {
            $io->error(sprintf('Échec de récupération du flux : %s', $e->getMessage()));

            return false;
        }

        /** @var array<string, PropertyBlockedPeriod> $existingByUid */
        $existingByUid = [];
        foreach ($this->blockedPeriodRepository->findBy(['syncSource' => $sync]) as $period) {
            $existingByUid[$period->getExternalUid()] = $period;
        }

        $created = 0;
        $updated = 0;
        foreach ($events as $event) {
            $reason = sprintf('Sync %s%s', $sync->getProviderName(), $event['summary'] !== null ? ' — ' . mb_substr($event['summary'], 0, 180) : '');

            // Événement « date pure » = nuitées occupées : on applique les
            // heures d'arrivée/départ du logement pour ne pas déborder sur la
            // nuit précédant le début ni sur la nuit du jour de fin (exclusif).
            [$startAt, $endAt] = $this->eventBounds($property, $event);

            $period = $existingByUid[$event['uid']] ?? null;
            if ($period !== null) {
                unset($existingByUid[$event['uid']]);
                if ($period->getStartAt() != $startAt || $period->getEndAt() != $endAt || $period->getReason() !== $reason) {
                    $period->setStartAt($startAt);
                    $period->setEndAt($endAt);
                    $period->setReason($reason);
                    ++$updated;
                }
            } else {
                $period = new PropertyBlockedPeriod();
                $period->setProperty($property);
                $period->setStartAt($startAt);
                $period->setEndAt($endAt);
                $period->setReason($reason);
                $period->setSyncSource($sync);
                $period->setExternalUid($event['uid']);
                $this->entityManager->persist($period);
                ++$created;
            }

            $this->warnOnReservationConflict($property, $event, $io);
        }

        // Les UID restants ont disparu du flux distant : on libère les nuitées
        $removed = count($existingByUid);
        foreach ($existingByUid as $obsolete) {
            $this->entityManager->remove($obsolete);
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d événement(s) : %d créé(s), %d mis à jour, %d supprimé(s).',
            count($events),
            $created,
            $updated,
            $removed,
        ));

        return true;
    }

    /**
     * @param array{uid: string, summary: ?string, start: \DateTimeImmutable, end: \DateTimeImmutable, allDay: bool} $event
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function eventBounds(\App\Entity\Property $property, array $event): array
    {
        if (!$event['allDay']) {
            return [$event['start'], $event['end']];
        }

        $checkinTime = $property->getCheckinTime();
        $checkoutTime = $property->getCheckoutTime();

        return [
            $event['start']->setTime(
                $checkinTime !== null ? (int) $checkinTime->format('G') : 15,
                $checkinTime !== null ? (int) $checkinTime->format('i') : 0,
            ),
            $event['end']->setTime(
                $checkoutTime !== null ? (int) $checkoutTime->format('G') : 11,
                $checkoutTime !== null ? (int) $checkoutTime->format('i') : 0,
            ),
        ];
    }

    /**
     * @param array{uid: string, summary: ?string, start: \DateTimeImmutable, end: \DateTimeImmutable, allDay: bool} $event
     */
    private function warnOnReservationConflict(\App\Entity\Property $property, array $event, SymfonyStyle $io): void
    {
        $overlapping = $this->reservationRepository->findOverlappingForProperty(
            $property,
            $event['start']->setTime(0, 0),
            $event['end']->setTime(0, 0)->modify('-1 day'),
            ['confirmed'],
        );

        foreach ($overlapping as $reservation) {
            $io->warning(sprintf(
                'Conflit : l\'événement distant %s (%s → %s) chevauche la réservation confirmée %s (%s → %s). Double-booking à arbitrer par l\'hôte.',
                $event['uid'],
                $event['start']->format('d/m/Y'),
                $event['end']->format('d/m/Y'),
                $reservation->getId(),
                $reservation->getCheckinDate()->format('d/m/Y'),
                $reservation->getCheckoutDate()->format('d/m/Y'),
            ));
        }
    }
}
