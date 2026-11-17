<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Copy;
use App\Entity\Loan;
use App\Entity\Member;
use App\Enum\CopyStatus;
use App\Repository\CopyRepository;
use App\Repository\LoanRepository;
use App\Repository\MemberRepository;
use App\Service\LoanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_LIBRARIAN')]
class LibrarianDeskController extends AbstractController
{
    #[Route('/admin/guichet', name: 'app_librarian_desk', methods: ['GET'])]
    public function index(LoanRepository $loanRepository): Response
    {
        $recentLoans = $loanRepository->findBy(
            ['returnedAt' => null],
            ['borrowedAt' => 'DESC'],
            limit: 8
        );

        return $this->render('admin/desk.html.twig', [
            'recentLoans' => $recentLoans,
        ]);
    }

    #[Route('/admin/guichet/emprunt', name: 'app_librarian_desk_borrow', methods: ['POST'])]
    public function borrow(
        Request $request,
        MemberRepository $memberRepository,
        CopyRepository $copyRepository,
        LoanService $loanService
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('desk_borrow', $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_librarian_desk');
        }

        $membershipNumber = trim((string) $request->request->get('membershipNumber'));
        $barcode = trim((string) $request->request->get('barcode'));

        $member = $memberRepository->findOneBy(['membershipNumber' => $membershipNumber]);
        if ($member === null) {
            $this->addFlash('error', sprintf('Aucun adhérent trouvé avec le numéro de carte « %s ».', $membershipNumber));
            return $this->redirectToRoute('app_librarian_desk');
        }

        $copy = $copyRepository->findOneBy(['barcode' => $barcode]);
        if ($copy === null) {
            $this->addFlash('error', sprintf('Aucun exemplaire trouvé avec le code-barres « %s ».', $barcode));
            return $this->redirectToRoute('app_librarian_desk');
        }

        try {
            $loan = $loanService->borrow($member, $copy);
            $this->addFlash('success', sprintf(
                'Prêt enregistré : « %s » remis à %s (%s). Retour attendu avant le %s.',
                $loan->getCopy()->getEdition()->getTitle(),
                $member->getUser()->getEmail(),
                $member->getMembershipNumber(),
                $loan->getDueDate()->format('d/m/Y')
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_librarian_desk');
    }

    #[Route('/admin/guichet/retour', name: 'app_librarian_desk_return', methods: ['POST'])]
    public function returnLoan(
        Request $request,
        CopyRepository $copyRepository,
        LoanRepository $loanRepository,
        LoanService $loanService
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('desk_return', $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_librarian_desk');
        }

        $barcode = trim((string) $request->request->get('barcode'));

        $copy = $copyRepository->findOneBy(['barcode' => $barcode]);
        if ($copy === null) {
            $this->addFlash('error', sprintf('Aucun exemplaire trouvé avec le code-barres « %s ».', $barcode));
            return $this->redirectToRoute('app_librarian_desk');
        }

        $loan = $loanRepository->findActiveLoanByCopy($copy);
        if ($loan === null) {
            $this->addFlash('error', sprintf('L\'exemplaire « %s » n\'est pas actuellement enregistré comme prêté.', $barcode));
            return $this->redirectToRoute('app_librarian_desk');
        }

        try {
            $title = $copy->getEdition()->getTitle();
            $penalty = $loanService->return($loan);

            $msg = sprintf('Retour validé pour « %s » (code-barres : %s).', $title, $barcode);

            if ($penalty !== null) {
                $msg .= sprintf(' Retard constaté : pénalité de %s DH générée.', $penalty->getAmount());
            }

            if ($copy->getStatus() === CopyStatus::HELD_FOR_PICKUP) {
                $msg .= ' [RÉSERVATION] Ouvrage mis de côté au guichet pour une réservation adhérent en attente !';
            } elseif ($copy->getStatus() === CopyStatus::IN_TRANSIT) {
                $msg .= ' [TRANSFERT] Ouvrage réservé sur un autre site : transfert inter-médiathèques déclenché !';
            }

            $this->addFlash('success', $msg);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_librarian_desk');
    }
}
