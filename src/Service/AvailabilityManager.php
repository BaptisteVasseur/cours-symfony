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
        foreach ($this->eachDay($from, $to) as $day) {
            $key = $day->format('Y-m-d');
            if (isset($existing[$key])) {
                continue;
            }

            $availability = new PropertyAvailability();
            $availability->setProperty($property);
            $availability->setAvailableDate($day);
            $availability->setIsAvailable(false);
            $availability->setSource('manual');
            $this->em->persist($availability);
            ++$count;
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

    /**
     * @return iterable<\DateTimeImmutable>
     */
    private function eachDay(\DateTimeImmutable $from, \DateTimeImmutable $to): iterable
    {
        $period = new \DatePeriod($from, new \DateInterval('P1D'), $to->modify('+1 day'));

        foreach ($period as $day) {
            yield \DateTimeImmutable::createFromInterface($day);
        }
    }
}
