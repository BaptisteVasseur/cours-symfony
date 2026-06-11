<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Dto\BookingRequest;
use App\Form\BookingRequestType;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'properties' => $propertyRepository->findForListing(),
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    public function detail(Property $property, PropertyRepository $propertyRepository, Request $request): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;
        $bookingRequest = new BookingRequest();
        $bookingRequest->checkinDate = $this->parseDate($request->query->get('checkin'));
        $bookingRequest->checkoutDate = $this->parseDate($request->query->get('checkout'));
        $bookingRequest->guestsCount = max(1, $request->query->getInt('guests', 1));
        $bookingForm = $this->createForm(BookingRequestType::class, $bookingRequest, [
            'action' => $this->generateUrl('app_reservation_create', ['id' => $property->getId()]),
        ]);

        return $this->render('home/logement.html.twig', [
            'property' => $property,
            'bookingForm' => $bookingForm,
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $checkin = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));
        $guests = $request->query->has('guests') ? max(1, $request->query->getInt('guests', 1)) : null;
        $destination = trim((string) $request->query->get('destination', ''));

        return $this->render('home/search.html.twig', [
            'properties' => $propertyRepository->searchAvailable(
                $destination !== '' ? $destination : null,
                $checkin,
                $checkout,
                $guests,
            ),
            'checkin' => $checkin?->format('Y-m-d'),
            'checkout' => $checkout?->format('Y-m-d'),
            'guests' => $guests ?? 1,
            'destination' => $destination,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(): Response
    {
        return $this->render('home/register.html.twig');
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
