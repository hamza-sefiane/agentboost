<?php

namespace App\Entity;

use App\Repository\CityReferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityReferenceRepository::class)]
class CityReference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120)]
    private ?string $city = null;

    #[ORM\Column(length: 120)]
    private ?string $normalizedCity = null;

    #[ORM\Column(length: 10)]
    private ?string $inseeCode = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getNormalizedCity(): ?string
    {
        return $this->normalizedCity;
    }

    public function setNormalizedCity(string $normalizedCity): static
    {
        $this->normalizedCity = $normalizedCity;

        return $this;
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
}
