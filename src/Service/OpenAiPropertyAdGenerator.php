<?php

namespace App\Service;

use App\Entity\Property;

final class OpenAiPropertyAdGenerator
{
    public function __construct(
        private readonly AiClientInterface $aiClient,
    ) {
    }

    public function generate(Property $property): string
    {
        $prompt = sprintf(
            <<<PROMPT
Rédige une annonce immobilière professionnelle et attractive.

Type de bien : %s
Ville : %s
Code postal : %s
Surface : %d m²
Nombre de pièces : %d
Parking : %s

Contraintes :
- Style professionnel
- Ton premium
- Texte fluide
- Maximum 120 mots
- Pas de liste à puces
- Mettre en avant les points forts du bien
PROMPT,
            $property->getType(),
            $property->getCity(),
            $property->getPostalCode(),
            $property->getSurface(),
            $property->getRooms(),
            $property->hasParking() ? 'Oui' : 'Non',
        );

        return $this->aiClient->generateText($prompt);
    }
}