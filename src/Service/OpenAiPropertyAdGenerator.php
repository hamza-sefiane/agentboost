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
        'prestations de qualité',
        'divers aménagements',
        'clientèle variée',
        'selon vos besoins',
        'saura répondre aux attentes',
        'ville dynamique',
        'bien d’exception',
        'bien d\'exception',
        'à visiter sans tarder',
        'idéalement situé',
        'vous serez séduit',
        'parfait pour',
        'répondre à vos besoins',
        'selon vos préférences',
        'de nombreuses possibilités',
        'saura répondre aux besoins',
        'idéal pour',
        'ideal pour',
        'vie familiale',
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
        'vidéo-surveillance',
        'video-surveillance',
        'surveillance',
        'balcon',
        'terrasse',
        'jardin',
        'cave',
        'ascenseur',
        'vue',
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
You are an experienced real estate copywriter.

Generate the response ONLY in %s.

Write a concise, professional real estate listing intended for publication on a real estate portal.

Allowed data:
- Property type: %s
- Address: %s
- City: %s
- Postal code: %s
- Surface: %d sqm
- Rooms: %d
- Parking: %s
- User details: %s

Writing rules:
- Use a professional real estate agency tone.
- Describe the property naturally and factually.
- Use only the information provided.
- Do not invent features or characteristics.
- Do not mention unavailable information.
- Do not mention the valuation or estimated price.
- Avoid exaggerated marketing language.
- Avoid superlatives.
- Write between 380 and 500 characters.
- Use 2 short paragraphs.
- End with a simple invitation to contact the agency.
- Avoid assumptions about the future buyer.
- Avoid expressions such as:
  "espace de vie agréable",
  "idéal pour",
  "projet de vie",
  "famille",
  "répondre à vos besoins".

Important:
You may mention the property type, address, city, postal code, surface, number of rooms and parking.
Do not mention school, shops, transport, balcony, terrace, garden, cellar, elevator, view, renovation work or fitted kitchen unless explicitly present in the user details.

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

    /**
     * @return array<string, string>
     */
    private function getLanguageConfig(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'language' => 'English',
                'yes' => 'Yes',
                'no' => 'No',
                'not_provided' => 'Not provided',
                'no_details' => 'No additional details',
                'fallback_parking' => 'A parking space is included.',
                'fallback_summary' => 'The property is described according to the information provided.',
                'fallback_contact' => 'Contact our agency for more information or to arrange a viewing.',
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
                'fallback_summary' => 'El inmueble se describe según la información indicada.',
                'fallback_contact' => 'Contacte con nuestra agencia para más información o para organizar una visita.',
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
                'fallback_summary' => 'Le bien est présenté selon les informations renseignées.',
                'fallback_contact' => 'Contactez notre agence pour plus d’informations ou pour organiser une visite.',
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
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = preg_replace('/^\s*[-•]\s*/m', '', $text) ?? $text;
        $text = preg_replace('/\s+([,.!?;:])/', '$1', $text) ?? $text;
        $text = preg_replace('/ {2,}/', ' ', $text) ?? $text;
        $text = preg_replace('/\n[ \t]+/', "\n", $text) ?? $text;

        if ($this->containsForbiddenTerm($text)) {
            return '';
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

    private function containsForbiddenTerm(string $text): bool
    {
        foreach (self::FORBIDDEN_TERMS as $term) {
            if (stripos($text, $term) !== false) {
                return true;
            }
        }

        return false;
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
