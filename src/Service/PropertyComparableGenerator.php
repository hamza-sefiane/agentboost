<?php

namespace App\Service;

use App\Entity\Property;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PropertyComparableGenerator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(Property $property): array
    {
        $estimate = $property->getEstimate() ?? 0;
        $surface = $property->getSurface();

        $comparables = [];

        for ($i = 0; $i < 3; $i++) {
            $surfaceVariation = random_int(-8, 8);
            $priceVariation = random_int(-25000, 25000);

            $comparableSurface = max(15, $surface + $surfaceVariation);

            $basePrice = max(50000, $estimate + $priceVariation);

            $comparables[] = [
                'type' => $property->getType(),
                'city' => $property->getCity(),
                'surface' => $comparableSurface,
                'lowPrice' => $basePrice - random_int(5000, 12000),
                'highPrice' => $basePrice + random_int(5000, 12000),
            ];
        }

        return $comparables;
    }

    public function generateMarketPosition(Property $property): string
    {
        $surface = $property->getSurface();

        if ($surface <= 45) {
            return $this->translator->trans(
                'comparables.market.small',
                [],
                'pdf'
            );
        }

        if ($surface <= 100) {
            return $this->translator->trans(
                'comparables.market.medium',
                [],
                'pdf'
            );
        }

        return $this->translator->trans(
            'comparables.market.large',
            [],
            'pdf'
        );
    }
}
