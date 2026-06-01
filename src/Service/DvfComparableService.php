<?php

namespace App\Service;

use App\Entity\Property;
use App\Repository\ComparableSaleRepository;

final class DvfComparableService
{
    public function __construct(
        private readonly ComparableSaleRepository $comparableSaleRepository,
        private readonly InseeResolverService $inseeResolver,
    ) {}

    public function findComparables(Property $property): array
    {
        $inseeCode = $this->inseeResolver->resolve(
            $property->getCity(),
            $property->getPostalCode()
        );

        if (!$inseeCode) {
            return [];
        }

        $sales = array_slice(
            $this->comparableSaleRepository->findSalesForValuation(
                $inseeCode,
                $property->getType(),
                max(1, $property->getSurface()),
                10
            ),
            0,
            3
        );

        return array_map(static fn($sale): array => [
            'type' => $sale->getPropertyType(),
            'city' => $property->getCity(),
            'surface' => $sale->getSurface(),
            'rooms' => null,
            'price' => $sale->getPrice(),
            'pricePerSqm' => $sale->getPricePerSqm(),
            'saleDate' => $sale->getSaleDate()?->format('d/m/Y'),
            'address' => null,
        ], $sales);
    }
}
