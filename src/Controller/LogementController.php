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
    public function index(Request $request, LogementRepository $logements): Response
    {
        $criteres = [
            'destination' => $request->query->get('destination', ''),
            'voyageurs' => $request->query->get('voyageurs', ''),
            'prix_max' => $request->query->get('prix_max', ''),
            'type' => $request->query->get('type', ''),
        ];

        return $this->render('logement/index.html.twig', [
            'criteres' => $criteres,
            'logements' => $logements->rechercherPublies($criteres),
        ]);
    }

    #[Route('/logements/{id}', name: 'app_logement_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Logement $logement): Response
    {
        return $this->render('logement/show.html.twig', [
            'logement' => $logement,
        ]);
    }
}
