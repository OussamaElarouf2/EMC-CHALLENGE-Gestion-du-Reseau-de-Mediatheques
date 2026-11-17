<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PenaltyReason;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'penalty')]
class Penalty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Loan::class)]
    #[ORM\JoinColumn(name: 'loan_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Loan $loan;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private string $amount;

    #[ORM\Column(enumType: PenaltyReason::class)]
    #[Assert\NotNull]
    private PenaltyReason $reason;

    #[ORM\Column]
    private bool $isPaid = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Loan $loan,
        string $amount,
        PenaltyReason $reason,
        \DateTimeImmutable $createdAt,
        bool $isPaid = false,
        ?\DateTimeImmutable $paidAt = null
    ) {
        $this->loan = $loan;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->createdAt = $createdAt;
        $this->isPaid = $isPaid;
        $this->paidAt = $paidAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoan(): Loan
    {
        return $this->loan;
    }

    public function setLoan(Loan $loan): self
    {
        $this->loan = $loan;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getReason(): PenaltyReason
    {
        return $this->reason;
    }

    public function setReason(PenaltyReason $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): self
    {
        $this->isPaid = $isPaid;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function markPaid(\DateTimeImmutable $paidAt): self
    {
        $this->isPaid = true;
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return sprintf('Pénalité #%d (%.2f MAD)', $this->id ?? 0, (float) $this->amount);
    }
}
