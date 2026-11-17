<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanRepository::class)]
#[ORM\Table(name: 'loan')]
#[ORM\Index(name: 'idx_loan_member_returned', columns: ['member_id', 'returned_at'])]
#[ORM\Index(name: 'idx_loan_due_date', columns: ['due_date'])]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Copy::class)]
    #[ORM\JoinColumn(name: 'copy_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Copy $copy;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Member $member;

    /**
     * Set to copyId while the loan is active, NULL after return.
     * Enforces at DB-level that a copy cannot have two active loans concurrently.
     */
    #[ORM\Column(type: Types::INTEGER, unique: true, nullable: true)]
    private ?int $activeCopyId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $borrowedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $returnedAt = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $extensionCount = 0;

    public function __construct(
        Copy $copy,
        Member $member,
        \DateTimeImmutable $borrowedAt,
        \DateTimeImmutable $dueDate,
        ?int $activeCopyId = null
    ) {
        $this->copy = $copy;
        $this->member = $member;
        $this->borrowedAt = $borrowedAt;
        $this->dueDate = $dueDate;
        $this->activeCopyId = $activeCopyId ?? $copy->getId();
        $this->extensionCount = 0;
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

    public function getMember(): Member
    {
        return $this->member;
    }

    public function setMember(Member $member): self
    {
        $this->member = $member;
        return $this;
    }

    public function getActiveCopyId(): ?int
    {
        return $this->activeCopyId;
    }

    public function setActiveCopyId(?int $activeCopyId): self
    {
        $this->activeCopyId = $activeCopyId;
        return $this;
    }

    public function getBorrowedAt(): \DateTimeImmutable
    {
        return $this->borrowedAt;
    }

    public function setBorrowedAt(\DateTimeImmutable $borrowedAt): self
    {
        $this->borrowedAt = $borrowedAt;
        return $this;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?\DateTimeImmutable $returnedAt): self
    {
        $this->returnedAt = $returnedAt;
        if ($returnedAt !== null) {
            $this->activeCopyId = null;
        }
        return $this;
    }

    public function getExtensionCount(): int
    {
        return $this->extensionCount;
    }

    public function setExtensionCount(int $extensionCount): self
    {
        $this->extensionCount = $extensionCount;
        return $this;
    }

    public function incrementExtensionCount(): self
    {
        ++$this->extensionCount;
        return $this;
    }

    public function markReturned(\DateTimeImmutable $returnedAt): self
    {
        $this->returnedAt = $returnedAt;
        $this->activeCopyId = null;
        return $this;
    }
}
