<?php

declare(strict_types=1);

namespace App\Service\ICal;

use App\Entity\PropertyICalSync;
use App\Service\Booking\PropertyAvailabilityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ICalSyncService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ICalImportParser $parser,
        private PropertyAvailabilityManager $availabilityManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function sync(PropertyICalSync $sync): int
    {
        $url = $sync->getICalUrl();
        $property = $sync->getProperty();

        if ($url === null || $property === null) {
            throw new \DomainException('Le flux iCal est incomplet.');
        }

        $content = $this->httpClient->request('GET', $url, [
            'timeout' => 15,
        ])->getContent();

        $today = new \DateTimeImmutable('today');
        $blocked = 0;

        foreach ($this->parser->parse($content) as $event) {
            $start = $event['start'];
            $end = $event['end'];

            if ($end <= $today) {
                continue;
            }

            if ($start < $today) {
                $start = $today;
            }

            $blocked += $this->availabilityManager->blockPeriod($property, $start, $end, null, null);
        }

        $sync->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $blocked;
    }
}
