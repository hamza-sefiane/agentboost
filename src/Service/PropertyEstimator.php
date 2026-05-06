<?php

declare(strict_types=1);

namespace App\Service;

final class PropertyEstimator
{
    private const BASE_PRICE_BY_TYPE = [
        'appartement' => 3900,
        'maison' => 3400,
        'terrain' => 450,
        'parking' => 1200,
    ];

    private const POSTAL_PRICE_BY_PREFIX = [
        '75' => 10500,
        '92' => 7600,
        '93' => 4300,
        '94' => 5200,
        '91' => 3900,
        '77' => 3400,
        '78' => 4300,
        '95' => 3600,
        '69' => 5200,
        '13' => 4200,
        '31' => 3900,
        '33' => 4600,
        '59' => 3300,
        '06' => 5200,
    ];

    public function estimate(array $data): array
    {
        $validated = $this->validate($data);

        if ($validated === null) {
            return [
                'estimate' => null,
                'adText' => null,
            ];
        }

        return [
            'estimate' => $this->calculateEstimate($validated),
            'adText' => null,
        ];
    }

    private function validate(array $data): ?array
    {
        $type = strtolower(trim((string) ($data['type'] ?? '')));
        $city = trim((string) ($data['city'] ?? ''));
        $postalCode = preg_replace('/\D/', '', (string) ($data['postalCode'] ?? ''));
        $surface = (int) ($data['surface'] ?? 0);
        $rooms = (int) ($data['rooms'] ?? 0);
        $parking = (bool) ($data['parking'] ?? false);

        if (
            $type === ''
            || $city === ''
            || strlen($postalCode) !== 5
            || $surface <= 0
            || !array_key_exists($type, self::BASE_PRICE_BY_TYPE)
        ) {
            return null;
        }

        if (in_array($type, ['appartement', 'maison'], true) && $rooms <= 0) {
            return null;
        }

        return [
            'type' => $type,
            'city' => mb_convert_case($city, MB_CASE_TITLE, 'UTF-8'),
            'postalCode' => $postalCode,
            'surface' => $surface,
            'rooms' => in_array($type, ['parking', 'terrain'], true) ? 0 : $rooms,
            'parking' => match ($type) {
                'parking' => true,
                'terrain' => false,
                default => $parking,
            },
        ];
    }

    private function calculateEstimate(array $data): int
    {
        $pricePerM2 = $this->resolvePricePerM2($data);

        $estimate = $data['surface'] * $pricePerM2;

        $estimate *= $this->typeCoefficient($data['type']);
        $estimate *= $this->surfaceCoefficient($data['surface']);
        $estimate *= $this->roomsCoefficient($data['type'], $data['rooms'], $data['surface']);

        if ($data['parking'] && !in_array($data['type'], ['parking', 'terrain'], true)) {
            $estimate += $this->parkingValue($data['postalCode']);
        }

        return $this->roundToNearest((int) round($estimate), 5000);
    }

    private function resolvePricePerM2(array $data): int
    {
        $prefix = substr($data['postalCode'], 0, 2);

        $localPrice = self::POSTAL_PRICE_BY_PREFIX[$prefix]
            ?? self::BASE_PRICE_BY_TYPE[$data['type']];

        return match ($data['type']) {
            'maison' => (int) round($localPrice * 0.92),
            'terrain' => (int) round($localPrice * 0.28),
            'parking' => self::BASE_PRICE_BY_TYPE['parking'],
            default => $localPrice,
        };
    }

    private function typeCoefficient(string $type): float
    {
        return match ($type) {
            'appartement' => 1.00,
            'maison' => 1.06,
            'terrain' => 1.00,
            'parking' => 1.00,
            default => 1.00,
        };
    }

    private function surfaceCoefficient(int $surface): float
    {
        return match (true) {
            $surface < 25 => 1.12,
            $surface < 50 => 1.06,
            $surface <= 120 => 1.00,
            $surface <= 220 => 0.94,
            default => 0.88,
        };
    }

    private function roomsCoefficient(string $type, int $rooms, int $surface): float
    {
        if (!in_array($type, ['appartement', 'maison'], true) || $rooms <= 0 || $surface <= 0) {
            return 1.00;
        }

        $surfacePerRoom = $surface / $rooms;

        return match (true) {
            $surfacePerRoom < 14 => 0.93,
            $surfacePerRoom <= 35 => 1.00,
            $surfacePerRoom <= 55 => 1.04,
            default => 1.08,
        };
    }

    private function parkingValue(string $postalCode): int
    {
        return match (substr($postalCode, 0, 2)) {
            '75', '92' => 30000,
            '93', '94', '91', '78', '95' => 18000,
            default => 12000,
        };
    }

    private function roundToNearest(int $value, int $step): int
    {
        return (int) (round($value / $step) * $step);
    }
}