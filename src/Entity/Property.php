<?php

namespace App\Entity;

use App\Repository\PropertyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
class Property
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $city;

    #[ORM\Column(length: 10)]
    private string $postalCode;

    #[ORM\Column(type: 'integer')]
    private int $surface;

    #[ORM\Column(type: 'integer')]
    private int $rooms;

    #[ORM\Column(type: 'boolean')]
    private bool $parking = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $estimate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $lowEstimate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $highEstimate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adText = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $extraDetails = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /**
     * @var Collection<int, PropertyPhoto>
     */
    #[ORM\OneToMany(
        targetEntity: PropertyPhoto::class,
        mappedBy: 'property',
        orphanRemoval: true,
        cascade: ['persist', 'remove']
    )]
    private Collection $photos;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = trim($type);

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = trim($city);

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = trim($postalCode);

        return $this;
    }

    public function getSurface(): int
    {
        return $this->surface;
    }

    public function setSurface(int $surface): self
    {
        $this->surface = max(0, $surface);

        return $this;
    }

    public function getRooms(): int
    {
        return $this->rooms;
    }

    public function setRooms(int $rooms): self
    {
        $this->rooms = max(0, $rooms);

        return $this;
    }

    public function hasParking(): bool
    {
        return $this->parking;
    }

    public function setParking(bool $parking): self
    {
        $this->parking = $parking;

        return $this;
    }

    public function getEstimate(): ?int
    {
        return $this->estimate;
    }

    public function setEstimate(?int $estimate): self
    {
        $this->estimate = $estimate !== null ? max(0, $estimate) : null;

        return $this;
    }

    public function getLowEstimate(): ?int
    {
        return $this->lowEstimate;
    }

    public function setLowEstimate(?int $lowEstimate): self
    {
        $this->lowEstimate = $lowEstimate !== null ? max(0, $lowEstimate) : null;

        return $this;
    }

    public function getHighEstimate(): ?int
    {
        return $this->highEstimate;
    }

    public function setHighEstimate(?int $highEstimate): self
    {
        $this->highEstimate = $highEstimate !== null ? max(0, $highEstimate) : null;

        return $this;
    }

    public function getAdText(): ?string
    {
        return $this->adText;
    }

    public function setAdText(?string $adText): self
    {
        $this->adText = $adText !== null ? trim($adText) : null;

        return $this;
    }

    public function getExtraDetails(): ?string
    {
        return $this->extraDetails;
    }

    public function setExtraDetails(?string $extraDetails): self
    {
        $this->extraDetails = $extraDetails !== null ? trim($extraDetails) : null;

        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, PropertyPhoto>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(PropertyPhoto $photo): self
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setProperty($this);
        }

        return $this;
    }

    public function removePhoto(PropertyPhoto $photo): self
    {
        if ($this->photos->removeElement($photo) && $photo->getProperty() === $this) {
            $photo->setProperty(null);
        }

        return $this;
    }
}