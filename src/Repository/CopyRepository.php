<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Enum\CopyStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Copy>
 */
class CopyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Copy::class);
    }

    /**
     * @return list<array{branch: Branch, total: int, available: int}>
     */
    public function findAvailabilityByBranch(Edition $edition): array
    {
        $branches = $this->getEntityManager()->getRepository(Branch::class)->findBy([], ['name' => 'ASC']);
        $availability = [];

        foreach ($branches as $branch) {
            $total = $this->count(['edition' => $edition, 'currentBranch' => $branch]);
            $available = $this->count(['edition' => $edition, 'currentBranch' => $branch, 'status' => CopyStatus::AVAILABLE]);

            $availability[] = [
                'branch' => $branch,
                'total' => $total,
                'available' => $available,
            ];
        }

        return $availability;
    }
}
