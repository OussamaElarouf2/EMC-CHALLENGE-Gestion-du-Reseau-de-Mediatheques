<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Branch;
use App\Entity\Contributor;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Penalty;
use App\Entity\Plan;
use App\Entity\Reservation;
use App\Entity\Transfer;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\ReservationStatus;
use App\Enum\TransferStatus;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function index(): Response
    {
        $stats = [
            'branches' => $this->em->getRepository(Branch::class)->count([]),
            'works' => $this->em->getRepository(Work::class)->count([]),
            'editions' => $this->em->getRepository(Edition::class)->count([]),
            'copies' => $this->em->getRepository(Copy::class)->count([]),
            'members' => $this->em->getRepository(Member::class)->count([]),
            'plans' => $this->em->getRepository(Plan::class)->count([]),
            'active_loans' => $this->em->getRepository(Loan::class)->count(['returnedAt' => null]),
            'waiting_reservations' => $this->em->getRepository(Reservation::class)->count(['status' => ReservationStatus::WAITING]),
            'ready_reservations' => $this->em->getRepository(Reservation::class)->count(['status' => ReservationStatus::READY]),
            'transfers' => $this->em->getRepository(Transfer::class)->count(['status' => TransferStatus::IN_TRANSIT]),
            'unpaid_penalties' => $this->em->getRepository(Penalty::class)->count(['isPaid' => false]),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Médiathèque — Administration')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-gauge');
        yield MenuItem::linkToRoute('Guichet Express (Prêt/Retour)', 'fa fa-cash-register', 'app_librarian_desk');
        yield MenuItem::linkToRoute('Catalogue public', 'fa fa-globe', 'app_catalogue');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(EditionCrudController::class, 'Éditions', 'fa fa-book');
        yield MenuItem::linkTo(CopyCrudController::class, 'Exemplaires', 'fa fa-barcode');
        yield MenuItem::linkTo(WorkCrudController::class, 'Œuvres', 'fa fa-layer-group');
        yield MenuItem::linkTo(ContributorCrudController::class, 'Contributeurs', 'fa fa-user-pen');

        yield MenuItem::section('Circulation');
        yield MenuItem::linkTo(LoanCrudController::class, 'Prêts', 'fa fa-hand-holding');
        yield MenuItem::linkTo(ReservationCrudController::class, 'Réservations', 'fa fa-clock');
        yield MenuItem::linkTo(TransferCrudController::class, 'Transferts', 'fa fa-truck');
        yield MenuItem::linkTo(PenaltyCrudController::class, 'Pénalités', 'fa fa-circle-exclamation');

        yield MenuItem::section('Adhérents & Abonnements');
        yield MenuItem::linkTo(MemberCrudController::class, 'Adhérents', 'fa fa-id-card');
        yield MenuItem::linkTo(PlanCrudController::class, 'Abonnements', 'fa fa-tags');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');

        yield MenuItem::section('Réseau');
        yield MenuItem::linkTo(BranchCrudController::class, 'Sites (Médiathèques)', 'fa fa-building');
    }
}
