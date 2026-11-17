<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calculates turnover rates globally, by media type, and by branch.
     *
     * @return array{
     *     totalCopies: int,
     *     totalLoans: int,
     *     globalTurnoverRate: float,
     *     byMediaType: list<array{type: MediaType, label: string, copies: int, loans: int, rate: float}>,
     *     byBranch: list<array{branch: Branch, copies: int, loans: int, rate: float}>
     * }
     */
    public function getTurnoverStats(): array
    {
        $totalCopies = (int) $this->em->getRepository(Copy::class)->count([]);
        $totalLoans = (int) $this->em->getRepository(Loan::class)->count([]);
        $globalTurnoverRate = $totalCopies > 0 ? round($totalLoans / $totalCopies, 2) : 0.0;

        // By Media Type
        $byMediaType = [];
        foreach (MediaType::cases() as $mediaType) {
            $copiesCount = (int) $this->em->createQueryBuilder()
                ->select('COUNT(c.id)')
                ->from(Copy::class, 'c')
                ->join('c.edition', 'e')
                ->where('e.mediaType = :type')
                ->setParameter('type', $mediaType)
                ->getQuery()
                ->getSingleScalarResult();

            $loansCount = (int) $this->em->createQueryBuilder()
                ->select('COUNT(l.id)')
                ->from(Loan::class, 'l')
                ->join('l.copy', 'c')
                ->join('c.edition', 'e')
                ->where('e.mediaType = :type')
                ->setParameter('type', $mediaType)
                ->getQuery()
                ->getSingleScalarResult();

            $rate = $copiesCount > 0 ? round($loansCount / $copiesCount, 2) : 0.0;

            $label = match ($mediaType) {
                MediaType::BOOK => 'Livres',
                MediaType::COMIC => 'Bandes Dessinées',
                MediaType::DVD => 'DVD & Vidéos',
            };

            $byMediaType[] = [
                'type' => $mediaType,
                'label' => $label,
                'copies' => $copiesCount,
                'loans' => $loansCount,
                'rate' => $rate,
            ];
        }

        // By Branch
        $byBranch = [];
        $branches = $this->em->getRepository(Branch::class)->findBy([], ['name' => 'ASC']);
        foreach ($branches as $branch) {
            $copiesCount = (int) $this->em->getRepository(Copy::class)->count(['currentBranch' => $branch]);
            $loansCount = (int) $this->em->createQueryBuilder()
                ->select('COUNT(l.id)')
                ->from(Loan::class, 'l')
                ->join('l.copy', 'c')
                ->where('c.currentBranch = :branch')
                ->setParameter('branch', $branch)
                ->getQuery()
                ->getSingleScalarResult();

            $rate = $copiesCount > 0 ? round($loansCount / $copiesCount, 2) : 0.0;

            $byBranch[] = [
                'branch' => $branch,
                'copies' => $copiesCount,
                'loans' => $loansCount,
                'rate' => $rate,
            ];
        }

        return [
            'totalCopies' => $totalCopies,
            'totalLoans' => $totalLoans,
            'globalTurnoverRate' => $globalTurnoverRate,
            'byMediaType' => $byMediaType,
            'byBranch' => $byBranch,
        ];
    }

    /**
     * Finds the most frequently borrowed editions.
     *
     * @return list<array{edition: Edition, loanCount: int}>
     */
    public function getMostBorrowedEditions(int $limit = 10): array
    {
        $rawCounts = $this->em->createQueryBuilder()
            ->select('IDENTITY(c.edition) as editionId', 'COUNT(l.id) as loanCount')
            ->from(Loan::class, 'l')
            ->join('l.copy', 'c')
            ->groupBy('editionId')
            ->orderBy('loanCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($rawCounts as $row) {
            $editionId = (int) $row['editionId'];
            $edition = $this->em->find(Edition::class, $editionId);
            if ($edition !== null) {
                $results[] = [
                    'edition' => $edition,
                    'loanCount' => (int) $row['loanCount'],
                ];
            }
        }

        return $results;
    }

    /**
     * Finds editions that have never been borrowed (0 loans recorded).
     *
     * @return list<Edition>
     */
    public function getNeverBorrowedEditions(int $limit = 20): array
    {
        return $this->em->createQueryBuilder()
            ->select('e', 'w')
            ->from(Edition::class, 'e')
            ->join('e.work', 'w')
            ->leftJoin('e.contributors', 'ec')
            ->leftJoin('ec.contributor', 'contrib')
            ->addSelect('ec', 'contrib')
            ->where('NOT EXISTS (
                SELECT 1 FROM ' . Loan::class . ' l
                JOIN l.copy c
                WHERE c.edition = e
            )')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts the total number of editions that have never been borrowed.
     */
    public function countNeverBorrowedEditions(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Edition::class, 'e')
            ->where('NOT EXISTS (
                SELECT 1 FROM ' . Loan::class . ' l
                JOIN l.copy c
                WHERE c.edition = e
            )')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calculates member active borrowing statistics.
     *
     * @return array{
     *     totalMembers: int,
     *     activeBorrowersCount: int,
     *     activeRate: float
     * }
     */
    public function getMemberActivityStats(): array
    {
        $totalMembers = (int) $this->em->getRepository(Member::class)->count([]);

        $activeBorrowersCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT l.member)')
            ->from(Loan::class, 'l')
            ->where('l.returnedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $activeRate = $totalMembers > 0 ? round(($activeBorrowersCount / $totalMembers) * 100, 1) : 0.0;

        return [
            'totalMembers' => $totalMembers,
            'activeBorrowersCount' => $activeBorrowersCount,
            'activeRate' => $activeRate,
        ];
    }
}
