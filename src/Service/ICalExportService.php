<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Repository\ReservationRepository;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\DateTime as iCalDateTime;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
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
            ->setParameter('property', $property)
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
                if ($checkinDate->format('Y-m-d') === $checkoutDate->format('Y-m-d')) {
                    $occurrence = new SingleDay(new Date($checkinDate));
                } else {
                    $occurrence = new MultiDay(new Date($checkinDate), new Date($checkoutDate));
                }
            }

            $guest = $reservation->getGuest();
            $guestName = 'Voyageur';
            $guestEmail = '';
            $guestPhone = '';
            if ($guest !== null) {
                $guestEmail = $guest->getEmail() ?? '';
                $guestPhone = $guest->getPhone() ?? '';
                $profile = $guest->getProfile();
                if ($profile !== null && ($profile->getFirstName() !== null || $profile->getLastName() !== null)) {
                    $guestName = trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? ''));
                } else {
                    $guestName = $guestEmail;
                }
            }

            $propertyTitle = $property->getTitle() ?? 'Logement';

            $summary = sprintf('Réservation : %s - %s', $propertyTitle, $guestName);

            $descriptionLines = [
                sprintf('Propriété : %s', $propertyTitle),
                sprintf('Client/Voyageur : %s', $guestName),
            ];
            if ($guestEmail !== '') {
                $descriptionLines[] = sprintf('Email : %s', $guestEmail);
            }
            if ($guestPhone !== '') {
                $descriptionLines[] = sprintf('Téléphone : %s', $guestPhone);
            }
            $descriptionLines[] = sprintf(
                'Dates : du %s au %s',
                $checkinDate->format('d/m/Y'),
                $checkoutDate->format('d/m/Y')
            );
            $descriptionLines[] = sprintf('Statut : %s', $reservation->getStatus());
            if ($reservation->getTotalPrice() !== null) {
                $descriptionLines[] = sprintf(
                    'Prix total : %s %s',
                    $reservation->getTotalPrice(),
                    $reservation->getCurrency() ?? 'EUR'
                );
            }
            $descriptionLines[] = sprintf('Nombre de voyageurs : %d', $reservation->getGuestsCount() ?? 1);

            $description = implode("\n", $descriptionLines);

            $event = (new Event($uid))
                ->setSummary($summary)
                ->setDescription($description)
                ->setOccurrence($occurrence);

            $events[] = $event;
        }

        $calendar = new Calendar($events);

        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);

        return (string) $calendarComponent;
    }
}
