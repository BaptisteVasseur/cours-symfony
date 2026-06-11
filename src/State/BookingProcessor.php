<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Booking;
use App\Exception\BookingConflictException;
use App\Service\BookingService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<Booking, mixed>
 */
final class BookingProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly Security $security,
    ) {
    }

    /**
     * @param Booking $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Booking) {
            return $data;
        }

        $property = $data->getProperty();
        $guest = $data->getGuest() ?? $this->security->getUser();
        $checkin = $data->getCheckinDate();
        $checkout = $data->getCheckoutDate();
        $guestsCount = $data->getGuestsCount();

        if (!$property) {
            throw new BadRequestHttpException('Le logement est obligatoire.');
        }
        if (!$guest) {
            throw new BadRequestHttpException('Le voyageur est obligatoire.');
        }
        if (!$checkin) {
            throw new BadRequestHttpException('La date d\'arrivée est obligatoire.');
        }
        if (!$checkout) {
            throw new BadRequestHttpException('La date de départ est obligatoire.');
        }
        if ($guestsCount === null) {
            throw new BadRequestHttpException('Le nombre de voyageurs est obligatoire.');
        }

        try {
            return $this->bookingService->create($property, $guest, $checkin, $checkout, $guestsCount);
        } catch (BookingConflictException|\App\Exception\UnavailableDatesException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }
}
