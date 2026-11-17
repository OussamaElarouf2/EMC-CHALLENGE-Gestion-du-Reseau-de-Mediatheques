<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContributorRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'edition_contributor')]
#[ORM\UniqueConstraint(name: 'uniq_edition_contributor_role', columns: ['edition_id', 'contributor_id', 'role'])]
class EditionContributor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Edition::class, inversedBy: 'contributors')]
    #[ORM\JoinColumn(name: 'edition_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Edition $edition;

    #[ORM\ManyToOne(targetEntity: Contributor::class)]
    #[ORM\JoinColumn(name: 'contributor_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Contributor $contributor;

    #[ORM\Column(enumType: ContributorRole::class)]
    #[Assert\NotNull]
    private ContributorRole $role;

    #[ORM\Column]
    #[Assert\Positive]
    private int $position;

    public function __construct(
        Edition $edition,
        Contributor $contributor,
        ContributorRole $role,
        int $position = 1
    ) {
        $this->edition = $edition;
        $this->contributor = $contributor;
        $this->role = $role;
        $this->position = $position;
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

    public function getContributor(): Contributor
    {
        return $this->contributor;
    }

    public function setContributor(Contributor $contributor): self
    {
        $this->contributor = $contributor;
        return $this;
    }

    public function getRole(): ContributorRole
    {
        return $this->role;
    }

    public function setRole(ContributorRole $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', $this->edition->getTitle(), $this->contributor->getName(), $this->role->value);
    }
}
