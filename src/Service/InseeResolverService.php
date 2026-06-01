<?php

namespace App\Service;

final class InseeResolverService
{
    public function resolve(string $city, string $postalCode): ?string
    {
        $city = mb_strtolower(trim($city));
        $postalCode = trim($postalCode);

        return match (true) {
            $city === 'yerres' && $postalCode === '91330' => '91691',
            default => null,
        };
    }
}
