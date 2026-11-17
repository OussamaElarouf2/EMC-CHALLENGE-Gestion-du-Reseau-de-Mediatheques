<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Finds the next waiting reservation for an edition (strict FIFO by createdAt ASC).
     */
    public function findFirstWaitingForEdition(Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->where('r.edition = :edition')
            ->andWhere('r.status = :status')
            ->setParameter('edition', $edition)
            ->setParameter('status', ReservationStatus::WAITING)
            ->orderBy('r.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Counts how many members are waiting in line for an edition.
     */
    public function countWaitingForEdition(Edition $edition): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.edition = :edition')
            ->andWhere('r.status = :status')
            ->setParameter('edition', $edition)
            ->setParameter('status', ReservationStatus::WAITING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Finds active reservations (WAITING or READY) for a member.
     *
     * @return list<Reservation>
     */
    public function findActiveByMember(Member $member): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.edition', 'e')
            ->join('r.pickupBranch', 'b')
            ->leftJoin('r.assignedCopy', 'c')
            ->addSelect('e', 'b', 'c')
            ->where('r.member = :member')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('member', $member)
            ->setParameter('activeStatuses', [ReservationStatus::WAITING, ReservationStatus::READY])
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Computes the 1-based FIFO rank of a waiting reservation.
     */
    public function getQueueRank(Reservation $reservation): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.edition = :edition')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt <= :createdAt')
            ->setParameter('edition', $reservation->getEdition())
            ->setParameter('status', ReservationStatus::WAITING)
            ->setParameter('createdAt', $reservation->getCreatedAt())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Finds an active reservation (WAITING or READY) for a specific member and edition.
     */
    public function findActiveByMemberAndEdition(Member $member, Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->where('r.member = :member')
            ->andWhere('r.edition = :edition')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('member', $member)
            ->setParameter('edition', $edition)
            ->setParameter('activeStatuses', [ReservationStatus::WAITING, ReservationStatus::READY])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds all reservations in READY status whose pickup deadline has passed.
     *
     * @return list<Reservation>
     */
    public function findExpiredReadyReservations(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.pickupDeadlineAt IS NOT NULL')
            ->andWhere('r.pickupDeadlineAt < :now')
            ->setParameter('status', ReservationStatus::READY)
            ->setParameter('now', $now)
            ->orderBy('r.pickupDeadlineAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
