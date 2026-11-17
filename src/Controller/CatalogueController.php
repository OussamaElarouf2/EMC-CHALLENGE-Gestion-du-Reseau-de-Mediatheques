<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Branch;
use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\User;
use App\Enum\MediaType;
use App\Enum\ReservationStatus;
use App\Repository\CopyRepository;
use App\Repository\EditionRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CatalogueController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[Route('/catalogue', name: 'app_catalogue', methods: ['GET'])]
    public function index(Request $request, EditionRepository $editionRepository): Response
    {
        $query = $request->query->get('q');
        $typeParam = $request->query->get('type');
        $page = max(1, $request->query->getInt('page', 1));

        $mediaType = null;
        if (is_string($typeParam) && $typeParam !== '') {
            $mediaType = MediaType::tryFrom($typeParam);
        }

        $searchResult = $editionRepository->searchCatalogue(
            query: is_string($query) ? $query : null,
            mediaType: $mediaType,
            page: $page,
            limit: 12
        );

        return $this->render('catalogue/index.html.twig', [
            'editions' => $searchResult['items'],
            'total' => $searchResult['total'],
            'page' => $searchResult['page'],
            'pages' => $searchResult['pages'],
            'currentQuery' => is_string($query) ? $query : '',
            'currentType' => $mediaType?->value ?? '',
            'mediaTypes' => MediaType::cases(),
        ]);
    }

    #[Route('/catalogue/{id}', name: 'app_catalogue_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Edition $edition,
        CopyRepository $copyRepository,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em
    ): Response {
        $availability = $copyRepository->findAvailabilityByBranch($edition);
        $branches = $em->getRepository(Branch::class)->findBy([], ['name' => 'ASC']);
        $waitingCount = $reservationRepository->count([
            'edition' => $edition,
            'status' => ReservationStatus::WAITING,
        ]);

        /** @var User|null $user */
        $user = $this->getUser();
        $member = null;
        if ($user !== null && $this->isGranted('ROLE_MEMBER')) {
            $member = $em->getRepository(Member::class)->findOneBy(['user' => $user]);
        }

        return $this->render('catalogue/show.html.twig', [
            'edition' => $edition,
            'availability' => $availability,
            'branches' => $branches,
            'waitingCount' => $waitingCount,
            'member' => $member,
        ]);
    }

    #[Route('/catalogue/{id}/reserve', name: 'app_catalogue_reserve', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_MEMBER')]
    public function reserve(
        Request $request,
        Edition $edition,
        EntityManagerInterface $em,
        ReservationQueueService $reservationQueueService
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reserve_edition_' . $edition->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_catalogue_show', ['id' => $edition->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $member = $em->getRepository(Member::class)->findOneBy(['user' => $user]);

        if ($member === null) {
            $this->addFlash('error', 'Votre compte n\'est pas rattaché à une fiche adhérent.');
            return $this->redirectToRoute('app_catalogue_show', ['id' => $edition->getId()]);
        }

        $pickupBranchId = $request->request->getInt('pickupBranchId');
        $pickupBranch = $em->getRepository(Branch::class)->find($pickupBranchId) ?? $member->getHomeBranch();

        try {
            $reservation = $reservationQueueService->reserve($member, $edition, $pickupBranch);
            $this->addFlash('success', sprintf(
                'Réservation enregistrée avec succès (site de retrait : %s). Statut actuel : %s.',
                $reservation->getPickupBranch()->getName(),
                $reservation->getStatus()->value === 'WAITING' ? 'En file d\'attente' : 'Disponible pour retrait'
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_catalogue_show', ['id' => $edition->getId()]);
    }
}
