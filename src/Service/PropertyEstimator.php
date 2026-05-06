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
            (in_array($type, ['appartement', 'maison'], true) && $rooms <= 0)
        ) {
            return null;
        }

        return [
            'type' => $type,
            'city' => ucfirst(strtolower($city)),
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

        if ($data['parking'] && !in_array($data['type'], ['parking', 'terrain'], true)) {
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

        $rooms = match ($data['type']) {
            'parking', 'terrain' => 'Non applicable',
            default => $data['rooms'] . ' pièces',
        };

        $parking = match ($data['type']) {
            'parking' => 'Oui',
            'terrain' => 'Non applicable',
            default => $data['parking'] ? 'Oui' : 'Non',
        };

        $price = number_format($estimate, 0, ',', ' ');

        $prompt = <<<PROMPT
Tu es un rédacteur immobilier professionnel.

Rédige une annonce immobilière réaliste, factuelle et prête à publier en français.

Données exactes :
- Type : {$type}
- Ville : {$data['city']} ({$data['postalCode']})
- Surface : {$data['surface']} m²
- Pièces : {$rooms}
- Parking : {$parking}
- Prix estimé : {$price} €

Format attendu : HTML simple uniquement.
Utilise seulement ces balises :
<h2>, <p>, <ul>, <li>, <strong>, <em>

Structure obligatoire :
<h2>Titre factuel</h2>
<p><strong>Accroche courte</strong></p>
<p>Description claire du bien.</p>
<ul>
  <li>Point fort basé uniquement sur les données fournies</li>
  <li>Point fort basé uniquement sur les données fournies</li>
</ul>
<p><em>Appel à l'action sobre.</em></p>

Contraintes strictes :
- Ne jamais inventer de caractéristiques absentes.
- Ne pas parler de luxe, haut de gamme, standing, jardin, cuisine équipée, balcon, terrasse, vue, rénovation, calme, luminosité ou quartier recherché si ce n'est pas fourni.
- Ne pas promettre un rendement.
- Ne pas utiliser de markdown.
- Ne pas utiliser ** ou #.
- Maximum 700 caractères.
PROMPT;

        try {
            $content = trim($this->aiClient->generateText($prompt));

            if ($content === '') {
                throw new RuntimeException('Empty AI response');
            }

            return $content;
        } catch (\Throwable) {
            return 'Annonce indisponible pour le moment.';
        }
    }
}