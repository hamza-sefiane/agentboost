<?php

namespace App\Service;

use App\Entity\ComparableSale;
use App\Repository\ComparableSaleRepository;

final class DvfMarketService
{
    public function __construct(
        private readonly ComparableSaleRepository $repository,
        private readonly InseeResolverService $inseeResolver,
    ) {}

    public function getAveragePricePerSqmForLocation(
        string $city,
        string $postalCode,
        string $propertyType,
        int $surface,
    ): ?int {
        $inseeCode = $this->inseeResolver->resolve($city, $postalCode);

        if (!$inseeCode) {
            return null;
        }

        return $this->getAveragePricePerSqm(
            $inseeCode,
            $propertyType,
            $surface,
        );
    }

    public function getAveragePricePerSqm(
        string $inseeCode,
        string $propertyType,
        int $surface,
    ): ?int {
        $sales = $this->repository->findSalesForValuation(
            $inseeCode,
            $propertyType,
            $surface,
            10,
        );

        if ($sales === []) {
            return null;
        }

        $prices = array_map(
            static fn(ComparableSale $sale): int => $sale->getPricePerSqm(),
            $sales,
        );

        sort($prices);

        $count = count($prices);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return (int) round(($prices[$middle - 1] + $prices[$middle]) / 2);
        }

        return $prices[$middle];
    }
}
