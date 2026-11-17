<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReservationStatus;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
#[ORM\Index(name: 'idx_reservation_edition_status_created', columns: ['edition_id', 'status', 'created_at'])]
#[ORM\Index(name: 'idx_reservation_pickup_deadline', columns: ['pickup_deadline_at'])]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Edition::class)]
    #[ORM\JoinColumn(name: 'edition_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Edition $edition;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Member $member;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'pickup_branch_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Branch $pickupBranch;

    #[ORM\ManyToOne(targetEntity: Copy::class)]
    #[ORM\JoinColumn(name: 'assigned_copy_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Copy $assignedCopy = null;

    /**
     * Unique locking key: "{memberId}_{editionId}" while WAITING, AWAITING_TRANSFER, READY.
     * Reset to NULL when FULFILLED, EXPIRED, CANCELLED.
     */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $activeMemberEditionKey = null;

    #[ORM\Column(enumType: ReservationStatus::class)]
    #[Assert\NotNull]
    private ReservationStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pickupDeadlineAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $fulfilledAt = null;

    public function __construct(
        Edition $edition,
        Member $member,
        Branch $pickupBranch,
        ReservationStatus $status,
        \DateTimeImmutable $createdAt,
        ?string $activeMemberEditionKey = null
    ) {
        $this->edition = $edition;
        $this->member = $member;
        $this->pickupBranch = $pickupBranch;
        $this->status = $status;
        $this->createdAt = $createdAt;
        if ($activeMemberEditionKey !== null) {
            $this->activeMemberEditionKey = $activeMemberEditionKey;
        } elseif (in_array($status, [ReservationStatus::WAITING, ReservationStatus::READY], true) && $member->getId() !== null && $edition->getId() !== null) {
            $this->activeMemberEditionKey = self::computeActiveKey($member->getId(), $edition->getId());
        } else {
            $this->activeMemberEditionKey = null;
        }
    }

    public static function computeActiveKey(int $memberId, int $editionId): string
    {
        return sprintf('%d_%d', $memberId, $editionId);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEdition(): Edition
    {
        return $this->edition;
    }

    public function setEdition(Edition $edition): self
    {
        $this->edition = $edition;
        return $this;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function setMember(Member $member): self
    {
        $this->member = $member;
        return $this;
    }

    public function getPickupBranch(): Branch
    {
        return $this->pickupBranch;
    }

    public function setPickupBranch(Branch $pickupBranch): self
    {
        $this->pickupBranch = $pickupBranch;
        return $this;
    }

    public function getAssignedCopy(): ?Copy
    {
        return $this->assignedCopy;
    }

    public function setAssignedCopy(?Copy $assignedCopy): self
    {
        $this->assignedCopy = $assignedCopy;
        return $this;
    }

    public function getActiveMemberEditionKey(): ?string
    {
        return $this->activeMemberEditionKey;
    }

    public function setActiveMemberEditionKey(?string $activeMemberEditionKey): self
    {
        $this->activeMemberEditionKey = $activeMemberEditionKey;
        return $this;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(ReservationStatus $status): self
    {
        $this->status = $status;
        if (in_array($status, [ReservationStatus::FULFILLED, ReservationStatus::EXPIRED, ReservationStatus::CANCELLED], true)) {
            $this->activeMemberEditionKey = null;
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPickupDeadlineAt(): ?\DateTimeImmutable
    {
        return $this->pickupDeadlineAt;
    }

    public function setPickupDeadlineAt(?\DateTimeImmutable $pickupDeadlineAt): self
    {
        $this->pickupDeadlineAt = $pickupDeadlineAt;
        return $this;
    }

    public function getFulfilledAt(): ?\DateTimeImmutable
    {
        return $this->fulfilledAt;
    }

    public function setFulfilledAt(?\DateTimeImmutable $fulfilledAt): self
    {
        $this->fulfilledAt = $fulfilledAt;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('Réservation #%d — %s', $this->id ?? 0, $this->edition->getTitle());
    }
}
