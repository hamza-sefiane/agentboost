<?php

namespace App\Entity;

use App\Repository\PropertyPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyPhotoRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PropertyPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $filename = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $cloudinaryUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cloudinaryPublicId = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $premiumCover = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Property $property = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function initializeCreatedAt(): void
    {
        if (!$this->createdAt instanceof \DateTimeImmutable) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $filename = trim($filename);

        if ($filename === '') {
            throw new \InvalidArgumentException('Le nom du fichier photo ne peut pas être vide.');
        }

        $this->filename = $filename;

        return $this;
    }

    public function getCloudinaryUrl(): ?string
    {
        return $this->cloudinaryUrl;
    }

    public function setCloudinaryUrl(?string $cloudinaryUrl): self
    {
        $this->cloudinaryUrl = $cloudinaryUrl !== null ? trim($cloudinaryUrl) : null;

        return $this;
    }

    public function getCloudinaryPublicId(): ?string
    {
        return $this->cloudinaryPublicId;
    }

    public function setCloudinaryPublicId(?string $cloudinaryPublicId): self
    {
        $this->cloudinaryPublicId = $cloudinaryPublicId !== null ? trim($cloudinaryPublicId) : null;

        return $this;
    }

    public function getDisplayUrl(): string
    {
        return $this->cloudinaryUrl ?: '/uploads/properties/' . $this->filename;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = max(0, (int) $position);

        return $this;
    }

    public function isPremiumCover(): bool
    {
        return $this->premiumCover;
    }

    public function setPremiumCover(bool $premiumCover): self
    {
        $this->premiumCover = $premiumCover;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): self
    {
        $this->property = $property;

        return $this;
    }
}
