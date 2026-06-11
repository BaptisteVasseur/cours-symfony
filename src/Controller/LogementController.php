<?php

namespace App\Controller;

use App\Entity\Logement;
use App\Repository\LogementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LogementController extends AbstractController
{
    #[Route('/logements', name: 'app_logement_index', methods: ['GET'])]
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function index(Request $request, LogementRepository $logements): Response
    {
        $guests = $request->query->get('guests', $request->query->get('voyageurs', ''));
        $checkin = trim((string) $request->query->get('checkin', ''));
        $checkout = trim((string) $request->query->get('checkout', ''));
        $erreurs = [];

        $criteres = [
            'destination' => $request->query->get('destination', ''),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
            'voyageurs' => $guests,
            'prix_max' => $request->query->get('prix_max', ''),
            'type' => $request->query->get('type', ''),
        ];

        if (($checkin === '') xor ($checkout === '')) {
            $erreurs[] = 'Renseignez une date d arrivee et une date de depart pour filtrer par disponibilite.';
        } elseif ($checkin !== '' && $checkout !== '') {
            $dateArrivee = $this->creerDate($checkin);
            $dateDepart = $this->creerDate($checkout);

            if ($dateArrivee === null || $dateDepart === null || $dateArrivee >= $dateDepart) {
                $erreurs[] = 'Les dates de recherche sont invalides.';
                $criteres['checkin'] = '';
                $criteres['checkout'] = '';
            }
        }

        return $this->render('logement/index.html.twig', [
            'criteres' => $criteres,
            'erreurs' => $erreurs,
            'logements' => $logements->rechercherPublies($criteres),
        ]);
    }

    private function creerDate(string $valeur): ?\DateTimeImmutable
    {
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    #[Route('/logements/{id}', name: 'app_logement_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Logement $logement): Response
    {
        return $this->render('logement/show.html.twig', [
            'logement' => $logement,
        ]);
    }
}
