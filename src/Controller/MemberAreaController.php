<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use App\Service\LoanService;
use App\Service\ReservationQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
class MemberAreaController extends AbstractController
{
    #[Route('/espace-adherent', name: 'app_member_area', methods: ['GET'])]
    public function index(
        EntityManagerInterface $em,
        LoanRepository $loanRepository,
        ReservationRepository $reservationRepository,
        PenaltyRepository $penaltyRepository
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        $member = null;
        $activeLoans = [];
        $pastLoans = [];
        $activeReservationsWithRank = [];
        $unpaidPenalties = [];
        $totalUnpaidAmount = '0.00';

        if ($user !== null) {
            $member = $em->getRepository(Member::class)->findOneBy(['user' => $user]);
            if ($member !== null) {
                $activeLoans = $loanRepository->findActiveLoansByMember($member);
                $pastLoans = $loanRepository->findPastLoansByMember($member, 10);
                $rawReservations = $reservationRepository->findActiveByMember($member);

                foreach ($rawReservations as $reservation) {
                    $rank = $reservation->getStatus() === ReservationStatus::WAITING
                        ? $reservationRepository->getQueueRank($reservation)
                        : null;

                    $activeReservationsWithRank[] = [
                        'reservation' => $reservation,
                        'rank' => $rank,
                    ];
                }

                $unpaidPenalties = $penaltyRepository->findUnpaidByMember($member);
                $totalUnpaidAmount = $penaltyRepository->getTotalUnpaidAmount($member);
            }
        }

        return $this->render('member/index.html.twig', [
            'user' => $user,
            'member' => $member,
            'activeLoans' => $activeLoans,
            'pastLoans' => $pastLoans,
            'reservations' => $activeReservationsWithRank,
            'unpaidPenalties' => $unpaidPenalties,
            'totalUnpaidAmount' => $totalUnpaidAmount,
        ]);
    }

    #[Route('/espace-adherent/emprunts/{id}/prolonger', name: 'app_member_loan_extend', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('LOAN_EXTEND', subject: 'loan')]
    public function extendLoan(Loan $loan, Request $request, LoanService $loanService): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('extend_loan_' . $loan->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_member_area');
        }

        try {
            $extendedLoan = $loanService->extend($loan);
            $this->addFlash('success', sprintf(
                'L\'emprunt de « %s » a été prolongé avec succès jusqu\'au %s.',
                $extendedLoan->getCopy()->getEdition()->getTitle(),
                $extendedLoan->getDueDate()->format('d/m/Y')
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_member_area');
    }

    #[Route('/espace-adherent/reservations/{id}/annuler', name: 'app_member_reservation_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('RESERVATION_CANCEL', subject: 'reservation')]
    public function cancelReservation(
        Reservation $reservation,
        Request $request,
        ReservationQueueService $reservationQueueService
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cancel_reservation_' . $reservation->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_member_area');
        }

        $title = $reservation->getEdition()->getTitle();
        $reservationQueueService->cancel($reservation);

        $this->addFlash('success', sprintf('Votre réservation pour « %s » a bien été annulée.', $title));

        return $this->redirectToRoute('app_member_area');
    }
}
