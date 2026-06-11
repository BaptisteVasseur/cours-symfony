<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ICalUrlService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function generateICalUrl(Property $property): string
    {
        if (!$property->getIcalToken()) {
            throw new \InvalidArgumentException('Property does not have an iCal token');
        }

        return $this->urlGenerator->generate('app_ical_export', [
            'id' => $property->getId(),
            'token' => $property->getIcalToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function generateHostICalUrl(User $host): string
    {
        if (!$host->getHostIcalToken()) {
            throw new \InvalidArgumentException('Host does not have an iCal token');
        }

        return $this->urlGenerator->generate('app_host_ical_export', [
            'id' => $host->getId(),
            'token' => $host->getHostIcalToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
