<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Copy;
use App\Entity\Loan;
use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * Counts currently active (unreturned) loans for a member.
     */
    public function countActiveLoansByMember(Member $member): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.member = :member')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Finds the currently active loan for a given copy, if any.
     */
    public function findActiveLoanByCopy(Copy $copy): ?Loan
    {
        return $this->createQueryBuilder('l')
            ->where('l.copy = :copy')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('copy', $copy)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds currently active (unreturned) loans for a member.
     *
     * @return list<Loan>
     */
    public function findActiveLoansByMember(Member $member): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.copy', 'c')
            ->join('c.edition', 'e')
            ->addSelect('c', 'e')
            ->where('l.member = :member')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('member', $member)
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds past (returned) loans for a member.
     *
     * @return list<Loan>
     */
    public function findPastLoansByMember(Member $member, int $limit = 20): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.copy', 'c')
            ->join('c.edition', 'e')
            ->addSelect('c', 'e')
            ->where('l.member = :member')
            ->andWhere('l.returnedAt IS NOT NULL')
            ->setParameter('member', $member)
            ->orderBy('l.returnedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds active loans that are overdue as of a given timestamp.
     *
     * @return list<Loan>
     */
    public function findOverdueLoans(\DateTimeImmutable $now): array
    {
        $startOfDay = $now->setTime(0, 0, 0);

        return $this->createQueryBuilder('l')
            ->where('l.returnedAt IS NULL')
            ->andWhere('l.dueDate < :startOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
