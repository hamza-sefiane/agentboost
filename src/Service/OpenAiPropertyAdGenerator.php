<?php

namespace App\Service;

use App\Entity\Property;

final class OpenAiPropertyAdGenerator
{
    private const FORBIDDEN_TERMS = [
        'opportunité unique',
        'ne manquez pas',
        'coup de cœur',
        'rare sur le marché',
        'charme exceptionnel',
        'magnifique',
        'superbe',
        'cadre de vie agréable',
        'prestations de qualité',
        'divers aménagements',
        'cadre de vie',
        'clientèle variée',
        'surface généreuse',
        'selon vos besoins',
        'lieu de vie confortable',
        'saura répondre aux attentes',
        'ville dynamique',
        'bien d’exception',
        'bien d\'exception',
        'à visiter sans tarder',
        'idéalement situé',
        'vous serez séduit',
        'parfait pour',
    ];

    private const SENSITIVE_TERMS = [
        'école',
        'ecole',
        'commerce',
        'commerces',
        'gare',
        'transport',
        'métro',
        'metro',
        'bus',
        'tram',
        'centre-ville',
        'centre ville',
        'parc',
        'espaces verts',
        'résidence',
        'residence',
        'vidéo-surveillance',
        'video-surveillance',
        'surveillance',
        'balcon',
        'terrasse',
        'jardin',
        'cave',
        'ascenseur',
        'vue',
        'lumineux',
        'lumineuse',
        'travaux',
        'cuisine équipée',
        'cuisine equipee',
    ];

    public function __construct(
        private readonly AiClientInterface $aiClient,
    ) {}

    public function generate(Property $property, string $locale = 'fr'): string
    {
        $prompt = $this->buildPrompt($property, $locale);
        $text = $this->aiClient->generateText($prompt);
        $text = $this->cleanGeneratedText($text);

        if (!$this->isSafeGeneratedText($text, $property)) {
            return $this->buildFallbackAd($property, $locale);
        }

        return $text;
    }

    private function buildPrompt(Property $property, string $locale): string
    {
        $lang = $this->getLanguageConfig($locale);

        return sprintf(
            <<<PROMPT
You are a senior real estate copywriter.

Generate the response ONLY in %s.

Write a short, professional real estate listing.

Allowed data:
- Property type: %s
- Address: %s
- City: %s
- Postal code: %s
- Surface: %d sqm
- Rooms: %d
- Parking: %s
- User details: %s

Absolute rules:
- Use ONLY the allowed data.
- Never invent missing information.
- If information is not explicitly provided, do not mention it.
- Do not mention the price or valuation.
- Do not write a list.
- Do not use emojis.
- Do not use superlatives.
- Do not use cliché sales phrases.
- Use short sentences.
- Keep a professional, sober and credible tone.
- Maximum 480 characters including spaces.
- Maximum 3 short paragraphs.
- End with a simple contact sentence.

Important:
Avoid mentioning neighborhood, school, shops, station, transport, balcony, terrace, garden, cellar, elevator, residence, view, brightness, renovation work or fitted kitchen unless explicitly present in the user details.

PROMPT,
            $lang['language'],
            $property->getType(),
            $property->getAddress() ?: $lang['not_provided'],
            $property->getCity(),
            $property->getPostalCode(),
            $property->getSurface(),
            $property->getRooms(),
            $property->hasParking() ? $lang['yes'] : $lang['no'],
            $property->getExtraDetails() ?: $lang['no_details']
        );
    }

    private function getLanguageConfig(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'language' => 'English',
                'yes' => 'Yes',
                'no' => 'No',
                'not_provided' => 'Not provided',
                'no_details' => 'No additional details',
                'fallback_parking' => 'The property includes a parking space.',
                'fallback_summary' => 'This property offers a simple residential layout.',
                'fallback_contact' => 'Contact our agency for more information.',
                'fallback_location_at' => 'located at',
                'fallback_location_in' => 'located in',
                'rooms' => 'rooms',
                'sqm' => 'sqm',
            ],

            'es' => [
                'language' => 'Spanish',
                'yes' => 'Sí',
                'no' => 'No',
                'not_provided' => 'No especificado',
                'no_details' => 'Sin detalles adicionales',
                'fallback_parking' => 'El inmueble dispone de una plaza de aparcamiento.',
                'fallback_summary' => 'Este inmueble ofrece una distribución residencial sencilla.',
                'fallback_contact' => 'Contacte con nuestra agencia para más información.',
                'fallback_location_at' => 'situado en',
                'fallback_location_in' => 'situado en',
                'rooms' => 'habitaciones',
                'sqm' => 'm²',
            ],

            default => [
                'language' => 'French',
                'yes' => 'Oui',
                'no' => 'Non',
                'not_provided' => 'Non renseignée',
                'no_details' => 'Aucun détail supplémentaire',
                'fallback_parking' => 'Le bien dispose d’un stationnement.',
                'fallback_summary' => 'Ce bien présente une configuration simple à présenter et adaptée à un usage résidentiel.',
                'fallback_contact' => 'Contactez notre agence pour plus d’informations.',
                'fallback_location_at' => 'situé au',
                'fallback_location_in' => 'situé à',
                'rooms' => 'pièces',
                'sqm' => 'm²',
            ],
        };
    }

    private function cleanGeneratedText(string $text): string
    {
        $text = trim(strip_tags($text));
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = preg_replace('/^\s*[-•]\s*/m', '', $text) ?? $text;
        $text = preg_replace('/\s+([,.!?;:])/', '$1', $text) ?? $text;
        $text = preg_replace('/ {2,}/', ' ', $text) ?? $text;
        $text = preg_replace("/\n[ \t]+/", "\n", $text) ?? $text;

        foreach (self::FORBIDDEN_TERMS as $term) {
            if (stripos($text, $term) !== false) {
                return '';
            }
        }

        $text = trim($text);

        if (mb_strlen($text) > 520) {
            $text = mb_substr($text, 0, 520);
            $lastDotPosition = mb_strrpos($text, '.');

            if ($lastDotPosition !== false) {
                $text = mb_substr($text, 0, $lastDotPosition + 1);
            }
        }

        return trim($text);
    }

    private function isSafeGeneratedText(string $text, Property $property): bool
    {
        if ($text === '') {
            return false;
        }

        $allowedDetails = mb_strtolower((string) $property->getExtraDetails());
        $normalizedText = mb_strtolower($text);

        foreach (self::SENSITIVE_TERMS as $term) {
            if (
                str_contains($normalizedText, $term)
                && !str_contains($allowedDetails, $term)
            ) {
                return false;
            }
        }

        return true;
    }

    private function buildFallbackAd(Property $property, string $locale): string
    {
        $lang = $this->getLanguageConfig($locale);

        $location = trim(sprintf(
            '%s %s',
            $property->getPostalCode(),
            mb_strtoupper($property->getCity())
        ));

        $addressLine = $property->getAddress()
            ? sprintf('%s %s, %s.', $lang['fallback_location_at'], $property->getAddress(), $location)
            : sprintf('%s %s.', $lang['fallback_location_in'], $location);

        $parkingLine = $property->hasParking()
            ? "\n\n" . $lang['fallback_parking']
            : '';

        return sprintf(
            "%s %d %s de %d %s %s%s\n\n%s\n\n%s",
            $property->getType(),
            $property->getRooms(),
            $lang['rooms'],
            $property->getSurface(),
            $lang['sqm'],
            $addressLine,
            $parkingLine,
            $lang['fallback_summary'],
            $lang['fallback_contact']
        );
    }
}
