<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PropertyAvailability;
use App\Entity\PropertyICalSync;
use App\Repository\PropertyAvailabilityRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ICalImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ICalParser $parser,
        private readonly PropertyAvailabilityRepository $availabilities,
        private readonly ReservationRepository $reservations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function sync(PropertyICalSync $sync): array
    {
        $property = $sync->getProperty();
        if ($property === null) {
            throw new \RuntimeException('Source iCal sans logement associé.');
        }

        $sourceKey = 'ical:' . ($sync->getProviderName() ?? 'externe');

        $response = $this->httpClient->request('GET', (string) $sync->getICalUrl(), [
            'timeout' => 15,
            'max_duration' => 20,
        ]);
        $content = $response->getContent();

        $events = $this->parser->parseEvents($content);

        $wanted = [];
        foreach ($events as $event) {
            for ($day = $event['start']; $day < $event['end']; $day = $day->modify('+1 day')) {
                $wanted[$day->format('Y-m-d')] = $day;
            }
        }

        $existing = [];
        foreach ($this->availabilities->findByPropertyAndSource($property, $sourceKey) as $availability) {
            $existing[$availability->getAvailableDate()->format('Y-m-d')] = $availability;
        }

        $blocked = 0;
        $unblocked = 0;
        $conflicts = 0;

        foreach ($wanted as $key => $date) {
            if (isset($existing[$key])) {
                $existing[$key]->setIsAvailable(false);
                unset($existing[$key]);
                continue;
            }

            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setAvailableDate($date);
            $availability->setIsAvailable(false);
            $availability->setSource($sourceKey);
            $property->addAvailability($availability);
            $this->em->persist($availability);
            ++$blocked;

            if (\count($this->reservations->findConfirmedOverlapping($property, $date, $date->modify('+1 day'))) > 0) {
                ++$conflicts;
            }
        }

        foreach ($existing as $availability) {
            $this->em->remove($availability);
            ++$unblocked;
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return [
            'events' => \count($events),
            'blocked' => $blocked,
            'unblocked' => $unblocked,
            'conflicts' => $conflicts,
        ];
    }
}
