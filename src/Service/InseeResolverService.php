<?php

namespace App\Service;

use App\Repository\CityReferenceRepository;

final class InseeResolverService
{
    public function __construct(
        private readonly CityReferenceRepository $cityReferenceRepository,
    ) {}

    public function resolve(string $city, string $postalCode): ?string
    {
        return $this->cityReferenceRepository->findInseeCode($city, $postalCode);
    }
}
