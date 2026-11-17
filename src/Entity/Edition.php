<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CompletenessStatus;
use App\Enum\MediaType;
use App\Repository\EditionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EditionRepository::class)]
#[ORM\Table(name: 'edition')]
#[ORM\Index(name: 'idx_edition_title', columns: ['title'])]
class Edition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Work::class, inversedBy: 'editions')]
    #[ORM\JoinColumn(name: 'work_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private Work $work;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $isbn = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $publisher;

    #[ORM\Column]
    #[Assert\NotNull]
    private int $publicationYear;

    #[ORM\Column(enumType: MediaType::class)]
    #[Assert\NotNull]
    private MediaType $mediaType;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url]
    private ?string $coverUrl = null;

    #[ORM\Column(enumType: CompletenessStatus::class)]
    #[Assert\NotNull]
    private CompletenessStatus $completenessStatus;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, EditionContributor>
     */
    #[ORM\OneToMany(mappedBy: 'edition', targetEntity: EditionContributor::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $contributors;

    public function __construct(
        Work $work,
        string $title,
        string $publisher,
        int $publicationYear,
        MediaType $mediaType,
        CompletenessStatus $completenessStatus,
        \DateTimeImmutable $createdAt,
        ?string $isbn = null,
        ?string $coverUrl = null
    ) {
        $this->work = $work;
        $this->title = $title;
        $this->publisher = $publisher;
        $this->publicationYear = $publicationYear;
        $this->mediaType = $mediaType;
        $this->completenessStatus = $completenessStatus;
        $this->createdAt = $createdAt;
        $this->isbn = $isbn;
        $this->coverUrl = $coverUrl;
        $this->contributors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWork(): Work
    {
        return $this->work;
    }

    public function setWork(Work $work): self
    {
        $this->work = $work;
        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): self
    {
        $this->isbn = $isbn;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    public function setPublisher(string $publisher): self
    {
        $this->publisher = $publisher;
        return $this;
    }

    public function getPublicationYear(): int
    {
        return $this->publicationYear;
    }

    public function setPublicationYear(int $publicationYear): self
    {
        $this->publicationYear = $publicationYear;
        return $this;
    }

    public function getMediaType(): MediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(MediaType $mediaType): self
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): self
    {
        $this->coverUrl = $coverUrl;
        return $this;
    }

    public function getCompletenessStatus(): CompletenessStatus
    {
        return $this->completenessStatus;
    }

    public function setCompletenessStatus(CompletenessStatus $completenessStatus): self
    {
        $this->completenessStatus = $completenessStatus;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, EditionContributor>
     */
    public function getContributors(): Collection
    {
        return $this->contributors;
    }

    public function addContributor(EditionContributor $contributor): self
    {
        if (!$this->contributors->contains($contributor)) {
            $this->contributors->add($contributor);
            $contributor->setEdition($this);
        }

        return $this;
    }

    public function removeContributor(EditionContributor $contributor): self
    {
        $this->contributors->removeElement($contributor);
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (%d)', $this->title, $this->publicationYear);
    }
}
