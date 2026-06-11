<?php

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Logement;
use App\Entity\User;
use App\Service\DisponibiliteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote/annonces/{id}/calendrier')]
#[IsGranted('ROLE_HOTE')]
class HostLogementCalendarController extends AbstractController
{
    #[Route('', name: 'app_host_logement_calendar', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Logement $logement, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->verifierAccesHote($logement);

        $mois = $this->creerMois((string) $request->query->get('mois', ''));
        $debutMois = $mois->modify('first day of this month');
        $finMois = $debutMois->modify('first day of next month');
        $debutGrille = $debutMois->modify('-'.((int) $debutMois->format('N') - 1).' days');
        $finGrille = $finMois->modify('+'.(7 - (int) $finMois->modify('-1 day')->format('N')).' days');

        $disponibilites = $this->indexerDisponibilites(
            $entityManager,
            $logement,
            $debutGrille,
            $finGrille,
        );

        return $this->render('host/logement/calendar.html.twig', [
            'logement' => $logement,
            'mois' => $debutMois,
            'mois_precedent' => $debutMois->modify('-1 month')->format('Y-m'),
            'mois_suivant' => $debutMois->modify('+1 month')->format('Y-m'),
            'semaines' => $this->creerSemaines($debutGrille, $finGrille, $debutMois, $disponibilites),
        ]);
    }

    #[Route('/bloquer', name: 'app_host_logement_calendar_block', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function block(
        Logement $logement,
        Request $request,
        DisponibiliteService $disponibilites,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->verifierAccesHote($logement);

        if (!$this->isCsrfTokenValid('host_logement_calendar_block_'.$logement->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expire. Reessayez.');

            return $this->redirectToRoute('app_host_logement_calendar', ['id' => $logement->id]);
        }

        $dateDebut = $this->creerDate((string) $request->request->get('date_debut', ''));
        $dateFinIncluse = $this->creerDate((string) $request->request->get('date_fin', ''));
        $raison = trim((string) $request->request->get('raison_blocage', ''));
        $mois = (string) $request->request->get('mois', '');

        if ($dateDebut === null || $dateFinIncluse === null) {
            $this->addFlash('error', 'Renseignez une date de debut et une date de fin.');

            return $this->redirectToRoute('app_host_logement_calendar', ['id' => $logement->id, 'mois' => $mois]);
        }

        if ($dateDebut > $dateFinIncluse) {
            $this->addFlash('error', 'La date de fin doit etre posterieure ou egale a la date de debut.');

            return $this->redirectToRoute('app_host_logement_calendar', ['id' => $logement->id, 'mois' => $mois]);
        }

        $disponibilites->bloquerPeriode($logement, $dateDebut, $dateFinIncluse->modify('+1 day'), $raison);
        $logement->dateMiseAJour = new \DateTimeImmutable();
        $entityManager->flush();

        $this->addFlash('success', 'Periode bloquee dans le calendrier.');

        return $this->redirectToRoute('app_host_logement_calendar', ['id' => $logement->id, 'mois' => $mois]);
    }

    private function verifierAccesHote(Logement $logement): void
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($logement->hote->id !== $user->id) {
            throw $this->createAccessDeniedException();
        }
    }

    private function creerMois(string $valeur): \DateTimeImmutable
    {
        if ($valeur !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $valeur);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return new \DateTimeImmutable('first day of this month');
    }

    private function creerDate(string $valeur): ?\DateTimeImmutable
    {
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    /**
     * @return array<string, Disponibilite>
     */
    private function indexerDisponibilites(
        EntityManagerInterface $entityManager,
        Logement $logement,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): array {
        $resultats = $entityManager->createQueryBuilder()
            ->select('d')
            ->from(Disponibilite::class, 'd')
            ->andWhere('d.logement = :logement')
            ->andWhere('d.date >= :debut')
            ->andWhere('d.date < :fin')
            ->setParameter('logement', $logement)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();

        $disponibilites = [];
        foreach ($resultats as $disponibilite) {
            \assert($disponibilite instanceof Disponibilite);
            $disponibilites[$disponibilite->date->format('Y-m-d')] = $disponibilite;
        }

        return $disponibilites;
    }

    /**
     * @param array<string, Disponibilite> $disponibilites
     * @return list<list<array{date: \DateTimeImmutable, dans_mois: bool, disponibilite: ?Disponibilite}>>
     */
    private function creerSemaines(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        \DateTimeImmutable $mois,
        array $disponibilites,
    ): array {
        $semaines = [];
        $semaine = [];
        $date = $debut;

        while ($date < $fin) {
            $semaine[] = [
                'date' => $date,
                'dans_mois' => $date->format('Y-m') === $mois->format('Y-m'),
                'disponibilite' => $disponibilites[$date->format('Y-m-d')] ?? null,
            ];

            if (count($semaine) === 7) {
                $semaines[] = $semaine;
                $semaine = [];
            }

            $date = $date->modify('+1 day');
        }

        return $semaines;
    }
}
