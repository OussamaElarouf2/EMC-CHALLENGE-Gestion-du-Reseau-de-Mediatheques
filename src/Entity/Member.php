<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\Table(name: 'member')]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Plan $plan;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'home_branch_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Branch $homeBranch;

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $membershipNumber;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $subscriptionEndsAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        Plan $plan,
        Branch $homeBranch,
        string $membershipNumber,
        \DateTimeImmutable $subscriptionEndsAt,
        \DateTimeImmutable $createdAt
    ) {
        $this->user = $user;
        $this->plan = $plan;
        $this->homeBranch = $homeBranch;
        $this->membershipNumber = $membershipNumber;
        $this->subscriptionEndsAt = $subscriptionEndsAt;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function setPlan(Plan $plan): self
    {
        $this->plan = $plan;
        return $this;
    }

    public function getHomeBranch(): Branch
    {
        return $this->homeBranch;
    }

    public function setHomeBranch(Branch $homeBranch): self
    {
        $this->homeBranch = $homeBranch;
        return $this;
    }

    public function getMembershipNumber(): string
    {
        return $this->membershipNumber;
    }

    public function setMembershipNumber(string $membershipNumber): self
    {
        $this->membershipNumber = $membershipNumber;
        return $this;
    }

    public function getSubscriptionEndsAt(): \DateTimeImmutable
    {
        return $this->subscriptionEndsAt;
    }

    public function setSubscriptionEndsAt(\DateTimeImmutable $subscriptionEndsAt): self
    {
        $this->subscriptionEndsAt = $subscriptionEndsAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
