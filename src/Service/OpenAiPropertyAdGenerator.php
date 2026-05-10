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
    ) {
    }

    public function generate(Property $property): string
    {
        $prompt = $this->buildPrompt($property);
        $text = $this->aiClient->generateText($prompt);
        $text = $this->cleanGeneratedText($text);

        if (!$this->isSafeGeneratedText($text, $property)) {
            return $this->buildFallbackAd($property);
        }

        return $text;
    }

    private function buildPrompt(Property $property): string
    {
        return sprintf(
            <<<PROMPT
Tu es un rédacteur immobilier senior pour une agence française.

Rédige une annonce immobilière courte et professionnelle.

Données autorisées :
- Type de bien : %s
- Adresse : %s
- Ville : %s
- Code postal : %s
- Surface : %d m²
- Nombre de pièces : %d
- Parking : %s
- Détails fournis par l'utilisateur : %s

Règles absolues :
- Utiliser uniquement les données autorisées.
- Ne jamais inventer une information absente.
- Si une information n'est pas écrite dans les détails fournis, ne pas la mentionner.
- Ne pas mentionner le prix ni l'estimation.
- Ne pas écrire de liste.
- Ne pas utiliser d'emojis.
- Ne pas employer de superlatifs.
- Ne pas utiliser de phrases commerciales clichées.
- Phrases courtes.
- Ton professionnel, sobre et crédible.
- Maximum 480 caractères espaces compris.
- 3 paragraphes courts maximum.
- Terminer par une phrase simple de contact.

Important :
Tu dois éviter toute mention de quartier, école, commerce, gare, transport, balcon, terrasse, jardin, cave, ascenseur, résidence, vue, luminosité, travaux ou cuisine équipée si ce n'est pas explicitement présent dans les détails fournis.

PROMPT,
            $property->getType(),
            $property->getAddress() ?: 'Non renseignée',
            $property->getCity(),
            $property->getPostalCode(),
            $property->getSurface(),
            $property->getRooms(),
            $property->hasParking() ? 'Oui' : 'Non',
            $property->getExtraDetails() ?: 'Aucun détail supplémentaire'
        );
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

    private function buildFallbackAd(Property $property): string
    {
        $location = trim(sprintf(
            '%s %s',
            $property->getPostalCode(),
            mb_strtoupper($property->getCity())
        ));

        $addressLine = $property->getAddress()
            ? sprintf(' situé au %s, %s.', $property->getAddress(), $location)
            : sprintf(' situé à %s.', $location);

        $parkingLine = $property->hasParking()
            ? "\n\nLe bien dispose d’un stationnement."
            : '';

        return sprintf(
            "%s %d pièces de %d m²%s%s\n\nCe bien présente une configuration simple à présenter et adaptée à un usage résidentiel.\n\nContactez notre agence pour plus d’informations.",
            $property->getType(),
            $property->getRooms(),
            $property->getSurface(),
            $addressLine,
            $parkingLine
        );
    }
}