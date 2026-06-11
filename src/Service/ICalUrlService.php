<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Property;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ICalUrlService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $appDomain,
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
}
