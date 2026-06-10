<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Report;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/report')]
#[IsGranted('ROLE_ADMIN')]
final class AdminReportController extends AbstractController
{
    #[Route(name: 'app_admin_report_index', methods: ['GET'])]
    public function index(ReportRepository $reportRepository): Response
    {
        $reports = $reportRepository->findAllForListing();

        return $this->render('admin_report/index.html.twig', [
            'reports' => $reports,
            'total' => count($reports),
            'open' => count(array_filter($reports, static fn (Report $r): bool => $r->getStatus() === 'open')),
            'investigating' => count(array_filter($reports, static fn (Report $r): bool => $r->getStatus() === 'investigating')),
            'closed' => count(array_filter($reports, static fn (Report $r): bool => $r->getStatus() === 'closed')),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_report_show', methods: ['GET'])]
    public function show(Report $report): Response
    {
        return $this->render('admin_report/show.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/{id}/status', name: 'app_admin_report_status', methods: ['POST'])]
    public function updateStatus(Request $request, Report $report, EntityManagerInterface $entityManager): Response
    {
        $status = $request->getPayload()->getString('status');
        if (!$this->isCsrfTokenValid('report_status'.$report->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_admin_report_show', ['id' => $report->getId()], Response::HTTP_SEE_OTHER);
        }

        if (in_array($status, ['open', 'investigating', 'closed'], true)) {
            $report->setStatus($status);
            $entityManager->flush();
            $this->addFlash('success', 'Statut du signalement mis à jour.');
        }

        return $this->redirectToRoute('app_admin_report_show', ['id' => $report->getId()], Response::HTTP_SEE_OTHER);
    }
}
