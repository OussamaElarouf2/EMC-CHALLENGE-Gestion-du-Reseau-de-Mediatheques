<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CopyStatus;
use App\Repository\CopyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CopyRepository::class)]
#[ORM\Table(name: 'copy')]
#[ORM\Index(name: 'idx_copy_status', columns: ['status'])]
class Copy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Edition::class)]
    #[ORM\JoinColumn(name: 'edition_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Edition $edition;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'current_branch_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Branch $currentBranch;

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $barcode;

    #[ORM\Column(enumType: CopyStatus::class)]
    #[Assert\NotNull]
    private CopyStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Edition $edition,
        Branch $currentBranch,
        string $barcode,
        CopyStatus $status,
        \DateTimeImmutable $createdAt
    ) {
        $this->edition = $edition;
        $this->currentBranch = $currentBranch;
        $this->barcode = $barcode;
        $this->status = $status;
        $this->createdAt = $createdAt;
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

    public function getCurrentBranch(): Branch
    {
        return $this->currentBranch;
    }

    public function setCurrentBranch(Branch $currentBranch): self
    {
        $this->currentBranch = $currentBranch;
        return $this;
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function setBarcode(string $barcode): self
    {
        $this->barcode = $barcode;
        return $this;
    }

    public function getStatus(): CopyStatus
    {
        return $this->status;
    }

    public function setStatus(CopyStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
