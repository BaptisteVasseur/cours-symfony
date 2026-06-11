<?php

namespace App\Controller;

use App\Entity\Logement;
use App\Enum\LogementStatut;
use App\Enum\ModerationStatut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminLogementController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        $logements = $entityManager->getRepository(Logement::class);

        return $this->render('admin/dashboard.html.twig', [
            'annonces_en_attente' => $logements->count(['statut' => LogementStatut::EN_ATTENTE]),
            'annonces_publiees' => $logements->count(['statut' => LogementStatut::PUBLIE]),
            'annonces_brouillon' => $logements->count(['statut' => LogementStatut::BROUILLON]),
        ]);
    }

    #[Route('/annonces', name: 'app_admin_logement_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $logements = $entityManager
            ->getRepository(Logement::class)
            ->findBy(['statut' => LogementStatut::EN_ATTENTE], ['dateMiseAJour' => 'ASC']);

        return $this->render('admin/logement/index.html.twig', [
            'logements' => $logements,
        ]);
    }

    #[Route('/annonces/{id}', name: 'app_admin_logement_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Logement $logement): Response
    {
        return $this->render('admin/logement/show.html.twig', [
            'logement' => $logement,
        ]);
    }

    #[Route('/annonces/{id}/publier', name: 'app_admin_logement_publish', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function publish(Logement $logement, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_logement_publish_'.$logement->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expiree. Reessayez.');

            return $this->redirectToRoute('app_admin_logement_show', ['id' => $logement->id]);
        }

        if ($logement->statut !== LogementStatut::EN_ATTENTE) {
            $this->addFlash('error', 'Seules les annonces en attente peuvent etre publiees.');

            return $this->redirectToRoute('app_admin_logement_show', ['id' => $logement->id]);
        }

        $logement->statut = LogementStatut::PUBLIE;
        $logement->datePublication = new \DateTimeImmutable();
        $logement->dateMiseAJour = new \DateTimeImmutable();

        foreach ($logement->photos as $photo) {
            $photo->statutModeration = ModerationStatut::VALIDEE;
        }

        $entityManager->flush();

        $this->addFlash('success', 'Annonce publiee. Elle est maintenant visible dans la recherche.');

        return $this->redirectToRoute('app_admin_logement_index');
    }

    #[Route('/annonces/{id}/renvoyer-brouillon', name: 'app_admin_logement_return_draft', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function returnDraft(Logement $logement, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_logement_return_draft_'.$logement->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expiree. Reessayez.');

            return $this->redirectToRoute('app_admin_logement_show', ['id' => $logement->id]);
        }

        if ($logement->statut !== LogementStatut::EN_ATTENTE) {
            $this->addFlash('error', 'Seules les annonces en attente peuvent etre renvoyees en brouillon.');

            return $this->redirectToRoute('app_admin_logement_show', ['id' => $logement->id]);
        }

        $logement->statut = LogementStatut::BROUILLON;
        $logement->dateMiseAJour = new \DateTimeImmutable();
        $entityManager->flush();

        $this->addFlash('success', 'Annonce renvoyee en brouillon a l hote.');

        return $this->redirectToRoute('app_admin_logement_index');
    }
}
