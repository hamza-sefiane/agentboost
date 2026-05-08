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
    ];

    public function __construct(
        private readonly AiClientInterface $aiClient,
    ) {
    }

    public function generate(Property $property): string
    {
        $prompt = $this->buildPrompt($property);
        $text = $this->aiClient->generateText($prompt);

        return $this->cleanGeneratedText($text);
    }

    private function buildPrompt(Property $property): string
    {
        $extraDetails = $property->getExtraDetails();

        return sprintf(
            <<<PROMPT
Tu es un agent immobilier expérimenté.

Rédige une annonce immobilière professionnelle pour une agence.

Données strictement disponibles :
- Type de bien : %s
- Ville : %s
- Code postal : %s
- Surface : %d m²
- Nombre de pièces : %d
- Parking : %s
- Détails supplémentaires fournis par l'utilisateur : %s

Règles obligatoires :
- Utiliser uniquement les données ci-dessus.
- Ne jamais inventer de quartier, rue, transport, école, commerce, balcon, terrasse, jardin, étage, vue, luminosité, cave, ascenseur, cuisine équipée ou travaux.
- Ne pas mentionner le prix ni l'estimation.
- Ne pas écrire de liste à puces.
- Ne pas utiliser d'emojis.
- Ne pas utiliser de superlatifs.
- Ne pas utiliser de ton commercial agressif.
- Ne pas utiliser de phrases vagues.
- Ne pas utiliser de formulations typiques d'intelligence artificielle.
- Ne pas utiliser d'adjectifs décoratifs sans information concrète.
- Phrases courtes.
- Ton sobre, direct, crédible, professionnel.
- Style d'agence immobilière réelle.
- Maximum 520 caractères espaces compris.
- 3 à 4 courts paragraphes.
- Terminer par une phrase simple d'appel au contact.

Expressions interdites :
- opportunité unique
- ne manquez pas
- coup de cœur
- rare sur le marché
- magnifique
- superbe
- cadre de vie agréable
- divers aménagements
- cadre de vie
- clientèle variée
- surface généreuse
- selon vos besoins
- lieu de vie confortable
- ville dynamique
- saura répondre aux attentes

Structure attendue :
1. Présentation factuelle du bien.
2. Caractéristiques principales.
3. Usage possible.
4. Contact agence.

Exemple de style attendu :
Maison 3 pièces de 99 m² située à Créteil.

Le bien dispose d'un stationnement et d'une configuration fonctionnelle.

Convient à une résidence principale ou à un projet patrimonial.

Contactez notre agence pour plus d'informations.

PROMPT,
            $property->getType(),
            $property->getCity(),
            $property->getPostalCode(),
            $property->getSurface(),
            $property->getRooms(),
            $property->hasParking() ? 'Oui' : 'Non',
            $extraDetails ?: 'Aucun détail supplémentaire'
        );
    }

    private function cleanGeneratedText(string $text): string
    {
        $text = trim(strip_tags($text));
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        foreach (self::FORBIDDEN_TERMS as $term) {
            $text = str_ireplace($term, '', $text);
        }

        $text = preg_replace('/\s+([,.!?;:])/', '$1', $text) ?? $text;
        $text = preg_replace('/ {2,}/', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) > 600) {
            $text = mb_substr($text, 0, 600);
            $lastDotPosition = mb_strrpos($text, '.');

            if ($lastDotPosition !== false) {
                $text = mb_substr($text, 0, $lastDotPosition + 1);
            }
        }

        return trim($text);
    }
}