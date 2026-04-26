<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class PropertyEstimator
{
    public function __construct(
        private readonly AiClientInterface $aiClient
    ) {}

    public function estimate(array $data): array
    {
        $validated = $this->validate($data);

        if ($validated === null) {
            return [
                'estimate' => null,
                'adText' => 'Données insuffisantes pour estimer le bien.',
            ];
        }

        $estimate = $this->calculateEstimate($validated);

        return [
            'estimate' => $estimate,
            'adText' => $this->generateAdText($validated, $estimate),
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
            $type === '' ||
            $city === '' ||
            strlen($postalCode) !== 5 ||
            $surface <= 0 ||
            ($type !== 'parking' && $rooms <= 0)
        ) {
            return null;
        }

        return [
            'type' => $type,
            'city' => ucfirst(strtolower($city)),
            'postalCode' => $postalCode,
            'surface' => $surface,
            'rooms' => $type === 'parking' ? 0 : $rooms,
            'parking' => $type === 'parking' ? true : $parking,
        ];
    }

    private function calculateEstimate(array $data): int
    {
        $pricePerM2 = match ($data['type']) {
            'appartement' => 4000,
            'maison' => 3500,
            'terrain' => 1500,
            'parking' => 1200,
            default => 3000,
        };

        $estimate = $data['surface'] * $pricePerM2;

        if ($this->isLargeCity($data['city'])) {
            $estimate *= 1.10;
        }

        if ($data['parking'] && $data['type'] !== 'parking') {
            $estimate += 15000;
        }

        return (int) round($estimate);
    }

    private function isLargeCity(string $city): bool
    {
        return in_array(
            strtolower($city),
            ['paris', 'lyon', 'marseille', 'toulouse', 'bordeaux', 'lille', 'nice', 'créteil', 'creteil'],
            true
        );
    }

    private function generateAdText(array $data, int $estimate): string
    {
        $type = ucfirst($data['type']);
        $rooms = $data['type'] === 'parking' ? 'Non applicable' : $data['rooms'] . ' pièces';
        $parking = $data['parking'] ? 'Oui' : 'Non';
        $price = number_format($estimate, 0, ',', ' ');

        $prompt = <<<PROMPT
Tu es un rédacteur immobilier professionnel.

Rédige une annonce immobilière premium en français.

Données exactes :
- Type : {$type}
- Ville : {$data['city']} ({$data['postalCode']})
- Surface : {$data['surface']} m²
- Pièces : {$rooms}
- Parking : {$parking}
- Prix estimé : {$price} €

Format obligatoire :
1. Titre court et vendeur
2. Accroche professionnelle
3. Description claire
4. Points forts en liste courte
5. Appel à l'action

Contraintes :
- Ne pas inventer d’informations absentes
- Ne pas promettre un rendement
- Ne pas utiliser de superlatifs mensongers
- Maximum 900 caractères
- Ton professionnel, crédible, vendeur
PROMPT;

        try {
            $content = trim($this->aiClient->generate($prompt));

            if ($content === '') {
                throw new RuntimeException('Empty AI response');
            }

            return $content;
        } catch (\Throwable) {
            return 'Annonce indisponible pour le moment.';
        }
    }
}