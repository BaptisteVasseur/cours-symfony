<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\DateTimeValue;
use Eluceo\iCal\Domain\ValueObject\DateTime as iCalDateTime;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;

class ICalExportService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepo,
    ) {
    }

    public function generateCalendar(Property $property): string
    {
        $calendar = new Calendar([]);
        $events = [];

        $reservations = $this->reservationRepo->createQueryBuilder('r')
            ->where('r.property = :property')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('property', $property->getId(), 'uuid')
            ->setParameter('statuses', ['confirmed', 'completed'])
            ->getQuery()
            ->getResult();

        foreach ($reservations as $reservation) {
            $uid = new UniqueIdentifier((string) $reservation->getId() . '@airbnb-clone');

            $checkinDate = $reservation->getCheckinDate();
            $checkoutDate = $reservation->getCheckoutDate();

            $checkinTime = $property->getCheckinTime();
            $checkoutTime = $property->getCheckoutTime();

            if ($checkinTime !== null && $checkoutTime !== null) {
                $dtStart = new \DateTimeImmutable(
                    $checkinDate->format('Y-m-d') . ' ' . $checkinTime->format('H:i:s'),
                    new \DateTimeZone('UTC')
                );
                $dtEnd = new \DateTimeImmutable(
                    $checkoutDate->format('Y-m-d') . ' ' . $checkoutTime->format('H:i:s'),
                    new \DateTimeZone('UTC')
                );

                $occurrence = new TimeSpan(
                    new iCalDateTime($dtStart, false),
                    new iCalDateTime($dtEnd, false),
                );
            } else {
                $occurrence = new TimeSpan(
                    new DateTimeValue(new Date(
                        (int) $checkinDate->format('Y'),
                        (int) $checkinDate->format('m'),
                        (int) $checkinDate->format('d')
                    )),
                    new DateTimeValue(new Date(
                        (int) $checkoutDate->format('Y'),
                        (int) $checkoutDate->format('m'),
                        (int) $checkoutDate->format('d')
                    )),
                );
            }

            $event = (new Event($uid))
                ->setSummary('Réservé')
                ->setOccurrence($occurrence);

            $events[] = $event;
        }

        $exceptions = $property->getAvailabilityExceptions()->filter(
            fn ($e) => $e->getSource() === 'manual'
        );

        foreach ($exceptions as $exception) {
            $uid = new UniqueIdentifier('exception-' . (string) $exception->getId() . '@airbnb-clone');
            $exDate = $exception->getDate();

            $occurrence = new TimeSpan(
                new DateTimeValue(new Date(
                    (int) $exDate->format('Y'),
                    (int) $exDate->format('m'),
                    (int) $exDate->format('d')
                )),
                new DateTimeValue(new Date(
                    (int) $exDate->format('Y'),
                    (int) $exDate->format('m'),
                    (int) $exDate->format('d')
                )),
            );

            $summary = $exception->getReason() ?? 'Indisponible';

            $event = (new Event($uid))
                ->setSummary($summary)
                ->setOccurrence($occurrence);

            $events[] = $event;
        }

        $calendar = new Calendar($events);

        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);

        return (string) $calendarComponent;
    }
}
