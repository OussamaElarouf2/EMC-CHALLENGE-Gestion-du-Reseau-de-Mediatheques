<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Penalty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Penalty>
 */
class PenaltyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Penalty::class);
    }

    public function hasUnpaidPenalties(Member $member): bool
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.loan', 'l')
            ->where('l.member = :member')
            ->andWhere('p.isPaid = false')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return list<Penalty>
     */
    public function findUnpaidByMember(Member $member): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.loan', 'l')
            ->innerJoin('l.copy', 'c')
            ->innerJoin('c.edition', 'e')
            ->addSelect('l', 'c', 'e')
            ->where('l.member = :member')
            ->andWhere('p.isPaid = false')
            ->setParameter('member', $member)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalUnpaidAmount(Member $member): string
    {
        $sum = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->innerJoin('p.loan', 'l')
            ->where('l.member = :member')
            ->andWhere('p.isPaid = false')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();

        return $sum !== null ? (string) $sum : '0.00';
    }
}
