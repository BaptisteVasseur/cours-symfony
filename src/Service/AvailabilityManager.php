<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

class AvailabilityManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAvailabilityRepository $repository,
    ) {
    }

    public function block(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $this->setRange($property, $start, $end, false);
    }

    public function unblock(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $this->setRange($property, $start, $end, true);
    }

    private function setRange(Property $property, \DateTimeImmutable $start, \DateTimeImmutable $end, bool $available): void
    {
        $day = $start->setTime(0, 0);
        $end = $end->setTime(0, 0);

        while ($day <= $end) {
            $entry = $this->repository->findOneBy(['property' => $property, 'availableDate' => $day]);

            if ($entry === null) {
                // Pas de ligne pour ce jour : on en crée une seulement si on bloque
                if (!$available) {
                    $entry = (new PropertyAvailability())
                        ->setProperty($property)
                        ->setAvailableDate($day)
                        ->setIsAvailable(false);
                    $this->em->persist($entry);
                }
            } else {
                $entry->setIsAvailable($available);
            }

            $day = $day->modify('+1 day');
        }

        $this->em->flush();
    }
}