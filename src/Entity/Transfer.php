<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TransferStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'transfer')]
#[ORM\Index(name: 'idx_transfer_status', columns: ['status'])]
class Transfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Copy::class)]
    #[ORM\JoinColumn(name: 'copy_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Copy $copy;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'source_branch_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Branch $sourceBranch;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'target_branch_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Branch $targetBranch;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(name: 'reservation_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Reservation $reservation = null;

    #[ORM\Column(enumType: TransferStatus::class)]
    #[Assert\NotNull]
    private TransferStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    public function __construct(
        Copy $copy,
        Branch $sourceBranch,
        Branch $targetBranch,
        TransferStatus $status,
        \DateTimeImmutable $requestedAt,
        ?Reservation $reservation = null
    ) {
        $this->copy = $copy;
        $this->sourceBranch = $sourceBranch;
        $this->targetBranch = $targetBranch;
        $this->status = $status;
        $this->requestedAt = $requestedAt;
        $this->reservation = $reservation;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCopy(): Copy
    {
        return $this->copy;
    }

    public function setCopy(Copy $copy): self
    {
        $this->copy = $copy;
        return $this;
    }

    public function getSourceBranch(): Branch
    {
        return $this->sourceBranch;
    }

    public function setSourceBranch(Branch $sourceBranch): self
    {
        $this->sourceBranch = $sourceBranch;
        return $this;
    }

    public function getTargetBranch(): Branch
    {
        return $this->targetBranch;
    }

    public function setTargetBranch(Branch $targetBranch): self
    {
        $this->targetBranch = $targetBranch;
        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function getStatus(): TransferStatus
    {
        return $this->status;
    }

    public function setStatus(TransferStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): self
    {
        $this->requestedAt = $requestedAt;
        return $this;
    }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTimeImmutable $shippedAt): self
    {
        $this->shippedAt = $shippedAt;
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): self
    {
        $this->receivedAt = $receivedAt;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('Transfert #%d — %s', $this->id ?? 0, $this->copy->getBarcode());
    }
}
