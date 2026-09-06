<?php

namespace App\Entity;

use App\Repository\ComparableSaleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ComparableSaleRepository::class)]
class ComparableSale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $inseeCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 80)]
    private ?string $propertyType = null;

    #[ORM\Column]
    private ?int $surface = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $price = null;

    #[ORM\Column]
    private ?int $pricePerSqm = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $saleDate = null;

    #[ORM\Column(nullable: true)]
    private ?float $x = null;

    #[ORM\Column(nullable: true)]
    private ?float $y = null;

    #[ORM\Column(length: 50)]
    private ?string $source = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInseeCode(): ?string
    {
        return $this->inseeCode;
    }

    public function setInseeCode(string $inseeCode): static
    {
        $this->inseeCode = $inseeCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPropertyType(): ?string
    {
        return $this->propertyType;
    }

    public function setPropertyType(string $propertyType): static
    {
        $this->propertyType = $propertyType;

        return $this;
    }

    public function getSurface(): ?int
    {
        return $this->surface;
    }

    public function setSurface(int $surface): static
    {
        $this->surface = $surface;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPricePerSqm(): ?int
    {
        return $this->pricePerSqm;
    }

    public function setPricePerSqm(int $pricePerSqm): static
    {
        $this->pricePerSqm = $pricePerSqm;

        return $this;
    }

    public function getSaleDate(): ?\DateTime
    {
        return $this->saleDate;
    }

    public function setSaleDate(\DateTime $saleDate): static
    {
        $this->saleDate = $saleDate;

        return $this;
    }

    public function getX(): ?float
    {
        return $this->x;
    }

    public function setX(?float $x): static
    {
        $this->x = $x;

        return $this;
    }

    public function getY(): ?float
    {
        return $this->y;
    }

    public function setY(?float $y): static
    {
        $this->y = $y;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }
}
