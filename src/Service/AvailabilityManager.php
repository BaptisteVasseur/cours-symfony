<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AvailabilityManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $availabilities,
    ) {
    }

    public function block(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        [$from, $to] = $this->normalize($from, $to);

        $existing = [];
        foreach ($this->availabilities->findBlockedInRange($property, $from, $to) as $row) {
            $existing[$row->getAvailableDate()->format('Y-m-d')] = $row;
        }

        $count = 0;
        $cursor = $from;
        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            if (!isset($existing[$key])) {
                $availability = new PropertyAvailability();
                $availability->setProperty($property);
                $availability->setAvailableDate($cursor);
                $availability->setIsAvailable(false);
                $availability->setSource('manual');
                $this->em->persist($availability);
                ++$count;
            }

            $cursor = $cursor->modify('+1 day');
        }

        $this->em->flush();

        return $count;
    }

    public function unblock(Property $property, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        [$from, $to] = $this->normalize($from, $to);

        $count = 0;
        foreach ($this->availabilities->findManualInRange($property, $from, $to) as $row) {
            $this->em->remove($row);
            ++$count;
        }

        $this->em->flush();

        return $count;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function normalize(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
