<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'work')]
class Work
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $originalTitle;

    /**
     * @var Collection<int, Edition>
     */
    #[ORM\OneToMany(mappedBy: 'work', targetEntity: Edition::class)]
    private Collection $editions;

    public function __construct(string $originalTitle)
    {
        $this->originalTitle = $originalTitle;
        $this->editions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalTitle(): string
    {
        return $this->originalTitle;
    }

    public function setOriginalTitle(string $originalTitle): self
    {
        $this->originalTitle = $originalTitle;
        return $this;
    }

    /**
     * @return Collection<int, Edition>
     */
    public function getEditions(): Collection
    {
        return $this->editions;
    }

    public function addEdition(Edition $edition): self
    {
        if (!$this->editions->contains($edition)) {
            $this->editions->add($edition);
            $edition->setWork($this);
        }

        return $this;
    }

    public function removeEdition(Edition $edition): self
    {
        $this->editions->removeElement($edition);
        return $this;
    }

    public function __toString(): string
    {
        return $this->originalTitle;
    }
}
