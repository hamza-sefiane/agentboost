<?php

declare(strict_types=1);

namespace App\Service;

use OpenAI\Client;
use RuntimeException;

final class PropertyEstimator
{
    public function __construct(
        private readonly Client $openAiClient
    ) {}

    /**
     * @param array{
     *     type: string,
     *     city: string,
     *     surface: int,
     *     rooms: int
     * } $data
     *
     * @return array{
     *     estimate: int|null,
     *     adText: string
     * }
     */
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
        $adText   = $this->generateAdText($validated, $estimate);

        return [
            'estimate' => $estimate,
            'adText' => $adText,
        ];
    }

    /**
     * Validation métier minimale
     */
    private function validate(array $data): ?array
    {
        $type    = strtolower(trim($data['type'] ?? ''));
        $city    = trim($data['city'] ?? '');
        $surface = (int) ($data['surface'] ?? 0);
        $rooms   = (int) ($data['rooms'] ?? 0);

        if (
            $type === '' ||
            $city === '' ||
            $surface <= 0 ||
            $rooms <= 0
        ) {
            return null;
        }

        return [
            'type' => $type,
            'city' => $city,
            'surface' => $surface,
            'rooms' => $rooms,
        ];
    }

    /**
     * Estimation PUREMENT MÉTIER (sans IA)
     */
    private function calculateEstimate(array $data): int
    {
        $pricePerM2 = match ($data['type']) {
            'appartement' => 4000,
            'maison'      => 3500,
            'terrain'     => 1500,
            default       => 3000,
        };

        $estimate = $data['surface'] * $pricePerM2;

        if ($this->isLargeCity($data['city'])) {
            $estimate *= 1.10; // +10 %
        }

        return (int) round($estimate);
    }

    private function isLargeCity(string $city): bool
    {
        return in_array(
            strtolower($city),
            ['paris', 'lyon', 'marseille', 'toulouse', 'bordeaux', 'lille', 'nice'],
            true
        );
    }

    /**
     * IA = rédaction uniquement (encadrée)
     */
    private function generateAdText(array $data, int $estimate): string
    {
        $prompt = <<<PROMPT
Rédige une annonce immobilière professionnelle en français.

Données réelles :
- Type : {$data['type']}
- Ville : {$data['city']}
- Surface : {$data['surface']} m²
- Pièces : {$data['rooms']}
- Prix estimé : {$estimate} €

Contraintes STRICTES :
- Ton professionnel et vendeur
- Pas de promesses irréalistes
- Pas de chiffres inventés
- 5 lignes maximum
PROMPT;

        try {
            $response = $this->openAiClient->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');

            if ($content === '') {
                throw new RuntimeException('Empty AI response');
            }

            return $content;

        } catch (\Throwable) {
            return 'Annonce indisponible pour le moment.';
        }
    }
}
