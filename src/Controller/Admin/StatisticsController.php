<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_LIBRARIAN')]
class StatisticsController extends AbstractController
{
    #[Route('/admin/statistiques', name: 'app_admin_statistics', methods: ['GET'])]
    public function index(StatisticsService $statisticsService): Response
    {
        $turnoverStats = $statisticsService->getTurnoverStats();
        $mostBorrowed = $statisticsService->getMostBorrowedEditions(10);
        $neverBorrowed = $statisticsService->getNeverBorrowedEditions(15);
        $neverBorrowedCount = $statisticsService->countNeverBorrowedEditions();
        $memberActivity = $statisticsService->getMemberActivityStats();

        return $this->render('admin/statistics.html.twig', [
            'turnover' => $turnoverStats,
            'mostBorrowed' => $mostBorrowed,
            'neverBorrowed' => $neverBorrowed,
            'neverBorrowedCount' => $neverBorrowedCount,
            'memberActivity' => $memberActivity,
        ]);
    }
}
