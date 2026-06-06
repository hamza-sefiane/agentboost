<?php

namespace App\Service;

use App\Entity\Property;
use App\Repository\ComparableSaleRepository;

final class DvfComparableService
{
    private const ANALYZED_LIMIT = 10;
    private const DISPLAY_LIMIT = 3;

    public function __construct(
        private readonly ComparableSaleRepository $comparableSaleRepository,
        private readonly InseeResolverService $inseeResolver,
    ) {}

    public function findComparables(Property $property): array
    {
        return $this->analyze($property)['comparables'];
    }

    /**
     * @return array{
     *     comparables: array<int, array<string, mixed>>,
     *     stats: array<string, mixed>|null
     * }
     */
    public function analyze(Property $property): array
    {
        $inseeCode = $this->inseeResolver->resolve(
            $property->getCity(),
            $property->getPostalCode()
        );

        if (!$inseeCode) {
            return [
                'comparables' => [],
                'stats' => null,
            ];
        }

        $sales = $this->comparableSaleRepository->findSalesForValuation(
            $inseeCode,
            $property->getType(),
            max(1, $property->getSurface()),
            self::ANALYZED_LIMIT
        );

        $displaySales = array_slice($sales, 0, self::DISPLAY_LIMIT);

        return [
            'comparables' => array_map(
                fn($sale): array => $this->formatComparable($sale, $property),
                $displaySales
            ),
            'stats' => $this->buildStats($sales, $property),
        ];
    }

    private function formatComparable(object $sale, Property $property): array
    {
        return [
            'type' => $sale->getPropertyType(),
            'city' => $property->getCity(),
            'surface' => $sale->getSurface(),
            'rooms' => null,
            'price' => $sale->getPrice(),
            'pricePerSqm' => $sale->getPricePerSqm(),
            'saleDate' => $sale->getSaleDate()?->format('d/m/Y'),
            'address' => null,
        ];
    }

    /**
     * @param array<int, object> $sales
     *
     * @return array<string, mixed>|null
     */
    private function buildStats(array $sales, Property $property): ?array
    {
        if ($sales === []) {
            return null;
        }

        $pricesPerSqm = array_values(array_filter(
            array_map(
                static fn($sale): ?int => $sale->getPricePerSqm(),
                $sales
            ),
            static fn(?int $price): bool => $price !== null && $price > 0
        ));

        if ($pricesPerSqm === []) {
            return null;
        }

        sort($pricesPerSqm);

        $count = count($pricesPerSqm);
        $average = (int) round(array_sum($pricesPerSqm) / $count);
        $median = $this->median($pricesPerSqm);

        $estimatedPricePerSqm = $property->getSurface() > 0
            ? (int) round($property->getEstimate() / $property->getSurface())
            : null;

        $gapPercent = null;

        if ($estimatedPricePerSqm !== null && $median > 0) {
            $gapPercent = round((($estimatedPricePerSqm - $median) / $median) * 100, 1);
        }

        return [
            'count' => count($sales),
            'averagePricePerSqm' => $average,
            'medianPricePerSqm' => $median,
            'minPricePerSqm' => min($pricesPerSqm),
            'maxPricePerSqm' => max($pricesPerSqm),
            'estimatedPricePerSqm' => $estimatedPricePerSqm,
            'gapPercent' => $gapPercent,
        ];
    }

    /**
     * @param array<int, int> $values
     */
    private function median(array $values): int
    {
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}
