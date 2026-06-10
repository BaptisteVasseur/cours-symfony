<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ReviewReport;
use App\Repository\ReviewReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/review')]
#[IsGranted('ROLE_ADMIN')]
final class AdminReviewController extends AbstractController
{
    #[Route(name: 'app_admin_review_index', methods: ['GET'])]
    public function index(ReviewReportRepository $reviewReportRepository): Response
    {
        $reports = $reviewReportRepository->findAllForListing();

        return $this->render('admin_review/index.html.twig', [
            'reports' => $reports,
            'total' => count($reports),
            'pending' => count(array_filter($reports, static fn (ReviewReport $r): bool => !in_array($r->getStatus(), ['dismissed', 'upheld'], true))),
        ]);
    }

    #[Route('/{id}/dismiss', name: 'app_admin_review_dismiss', methods: ['POST'])]
    public function dismiss(Request $request, ReviewReport $report, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('review_dismiss'.$report->getId(), $request->getPayload()->getString('_token'))) {
            $report->setStatus('dismissed');
            $entityManager->flush();
            $this->addFlash('success', 'Signalement d\'avis classé sans suite.');
        }

        return $this->redirectToRoute('app_admin_review_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/uphold', name: 'app_admin_review_uphold', methods: ['POST'])]
    public function uphold(Request $request, ReviewReport $report, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('review_uphold'.$report->getId(), $request->getPayload()->getString('_token'))) {
            $report->setStatus('upheld');
            $entityManager->flush();
            $this->addFlash('success', 'Signalement d\'avis confirmé.');
        }

        return $this->redirectToRoute('app_admin_review_index', [], Response::HTTP_SEE_OTHER);
    }
}
