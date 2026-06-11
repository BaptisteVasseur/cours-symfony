<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AvailabilityException;
use App\Entity\PropertyICalSync;
use App\Repository\AvailabilityExceptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\VObject\Reader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ICalImportService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AvailabilityExceptionRepository $exceptionRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function importFromUrl(PropertyICalSync $sync): void
    {
        $property = $sync->getProperty();

        try {
            $response = $this->httpClient->request('GET', $sync->getICalUrl(), [
                'timeout' => 15,
                'headers' => ['Accept' => 'text/calendar, */*'],
            ]);

            $content = $response->getContent();
            $vcalendar = Reader::read($content, Reader::OPTION_FORGIVING | Reader::OPTION_IGNORE_INVALID_LINES);

            $importedDates = [];

            foreach ($vcalendar->select('VEVENT') as $vevent) {
                $dtstart = $vevent->DTSTART->getDateTime();
                $dtend = $vevent->DTEND !== null ? $vevent->DTEND->getDateTime() : $dtstart->modify('+1 day');

                $current = \DateTimeImmutable::createFromInterface($dtstart);
                $end = \DateTimeImmutable::createFromInterface($dtend);

                while ($current < $end) {
                    $dateKey = $current->format('Y-m-d');
                    $importedDates[$dateKey] = $current;
                    $current = $current->modify('+1 day');
                }
            }

            $existingExceptions = $this->exceptionRepo->findByPropertyAndSource($property, AvailabilityException::SOURCE_ICAL_IMPORT);
            $existingByDate = [];
            foreach ($existingExceptions as $ex) {
                $existingByDate[$ex->getDate()->format('Y-m-d')] = $ex;
            }

            foreach ($importedDates as $dateKey => $date) {
                if (isset($existingByDate[$dateKey])) {
                    unset($existingByDate[$dateKey]);
                } else {
                    $exception = new AvailabilityException();
                    $exception->setProperty($property);
                    $exception->setDate($date);
                    $exception->setSource(AvailabilityException::SOURCE_ICAL_IMPORT);
                    $exception->setReason('Import ' . $sync->getProviderName());
                    $this->em->persist($exception);
                }
            }

            foreach ($existingByDate as $orphan) {
                $this->em->remove($orphan);
            }

            $sync->setSyncStatus('success');
            $sync->setLastSyncAt(new \DateTimeImmutable());
            $sync->setErrorMessage(null);
        } catch (\Throwable $e) {
            $sync->setSyncStatus('error');
            $sync->setLastSyncAt(new \DateTimeImmutable());
            $sync->setErrorMessage(substr($e->getMessage(), 0, 1000));
        }

        $this->em->flush();
    }
}
