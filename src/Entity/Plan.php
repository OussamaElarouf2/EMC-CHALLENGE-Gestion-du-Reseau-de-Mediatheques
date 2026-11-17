<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'plan')]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column]
    #[Assert\Positive]
    private int $maxSimultaneousLoans;

    #[ORM\Column]
    #[Assert\Positive]
    private int $loanDurationDays;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $maxExtensions;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $extensionDurationDays;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $penaltyRatePerDay;

    public function __construct(
        string $name,
        int $maxSimultaneousLoans,
        int $loanDurationDays,
        int $maxExtensions,
        int $extensionDurationDays,
        string $penaltyRatePerDay
    ) {
        $this->name = $name;
        $this->maxSimultaneousLoans = $maxSimultaneousLoans;
        $this->loanDurationDays = $loanDurationDays;
        $this->maxExtensions = $maxExtensions;
        $this->extensionDurationDays = $extensionDurationDays;
        $this->penaltyRatePerDay = $penaltyRatePerDay;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getMaxSimultaneousLoans(): int
    {
        return $this->maxSimultaneousLoans;
    }

    public function setMaxSimultaneousLoans(int $maxSimultaneousLoans): self
    {
        $this->maxSimultaneousLoans = $maxSimultaneousLoans;
        return $this;
    }

    public function getLoanDurationDays(): int
    {
        return $this->loanDurationDays;
    }

    public function setLoanDurationDays(int $loanDurationDays): self
    {
        $this->loanDurationDays = $loanDurationDays;
        return $this;
    }

    public function getMaxExtensions(): int
    {
        return $this->maxExtensions;
    }

    public function setMaxExtensions(int $maxExtensions): self
    {
        $this->maxExtensions = $maxExtensions;
        return $this;
    }

    public function getExtensionDurationDays(): int
    {
        return $this->extensionDurationDays;
    }

    public function setExtensionDurationDays(int $extensionDurationDays): self
    {
        $this->extensionDurationDays = $extensionDurationDays;
        return $this;
    }

    public function getPenaltyRatePerDay(): string
    {
        return $this->penaltyRatePerDay;
    }

    public function setPenaltyRatePerDay(string $penaltyRatePerDay): self
    {
        $this->penaltyRatePerDay = $penaltyRatePerDay;
        return $this;
    }
}
