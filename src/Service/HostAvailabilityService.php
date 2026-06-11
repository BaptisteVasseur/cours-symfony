<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

final class HostAvailabilityService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $availabilities,
    ) {
    }

    public function blockPeriod(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $blocked = 0;

        for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
            $availability = $this->availabilities->findOneByPropertyAndDate($property, $day);
            if ($availability === null) {
                $availability = (new PropertyAvailability())
                    ->setProperty($property)
                    ->setAvailableDate($day);
                $this->em->persist($availability);
            }
            $availability->setIsAvailable(false);
            ++$blocked;
        }

        $this->em->flush();

        return $blocked;
    }
}
