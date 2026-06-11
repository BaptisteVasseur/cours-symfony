<?php


declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\PropertyAvailability;
use App\Entity\User;
use App\Repository\PropertyAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host/properties')]
#[IsGranted('ROLE_HOST')]
final class CalendarController extends AbstractController
{
    #[Route('/{id}/calendar', name: 'app_host_property_calendar', methods: ['GET', 'POST'])]
    public function calendar(
        Property                       $property,
        Request                        $request,
        PropertyAvailabilityRepository $propertyAvailabilityRepository,
        EntityManagerInterface         $entityManager,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($property->getHost() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gérer ce logement.');
        }

        if ($request->isMethod('POST')) {
            $startDateValue = $request->request->get('startDate');
            $endDateValue = $request->request->get('endDate');

            if (!$startDateValue || !$endDateValue) {
                $this->addFlash('error', 'Les dates sont obligatoires.');

                return $this->redirectToRoute('app_host_property_calendar', [
                    'id' => $property->getId(),
                ]);
            }

            $startDate = new \DateTimeImmutable($startDateValue);
            $endDate = new \DateTimeImmutable($endDateValue);

            if ($endDate < $startDate) {
                $this->addFlash('error', 'La date de fin doit être après la date de début.');

                return $this->redirectToRoute('app_host_property_calendar', [
                    'id' => $property->getId(),
                ]);
            }

            $currentDate = $startDate;

            while ($currentDate <= $endDate) {
                $availability = $propertyAvailabilityRepository->findOneForPropertyAndDate($property, $currentDate);

                if (!$availability instanceof PropertyAvailability) {
                    $availability = new PropertyAvailability();
                    $availability->setProperty($property);
                    $availability->setAvailableDate($currentDate);
                    $entityManager->persist($availability);
                }

                $availability->setIsAvailable(false);

                $currentDate = $currentDate->modify('+1 day');
            }

            $entityManager->flush();

            $this->addFlash('success', 'La période a bien été bloquée.');

            return $this->redirectToRoute('app_host_property_calendar', [
                'id' => $property->getId(),
            ]);
        }

        $year = (int)$request->query->get('year', date('Y'));
        $month = (int)$request->query->get('month', date('m'));

        $currentMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $firstDayOfMonth = $currentMonth;
        $lastDayOfMonth = $currentMonth->modify('last day of this month');

        $availabilities = $propertyAvailabilityRepository->findForPropertyBetween(
            $property,
            $firstDayOfMonth,
            $lastDayOfMonth
        );

        $availabilityByDate = [];

        foreach ($availabilities as $availability) {
            $availabilityByDate[$availability->getAvailableDate()->format('Y-m-d')] = $availability;
        }

        $calendarDays = [];
        $currentDate = $firstDayOfMonth;

        while ($currentDate <= $lastDayOfMonth) {
            $key = $currentDate->format('Y-m-d');
            $availability = $availabilityByDate[$key] ?? null;

            $calendarDays[] = [
                'date' => $currentDate,
                'availability' => $availability,
                'isAvailable' => $availability ? $availability->isAvailable() : true,
            ];

            $currentDate = $currentDate->modify('+1 day');
        }

        return $this->render('host/calendar.html.twig', [
            'property' => $property,
            'calendarDays' => $calendarDays,
            'availabilities' => $availabilities,
            'currentMonth' => $currentMonth,
            'previousMonth' => $currentMonth->modify('-1 month'),
            'nextMonth' => $currentMonth->modify('+1 month'),
        ]);
    }
}
