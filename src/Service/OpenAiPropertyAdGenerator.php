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
        $extraDetails = $property->getExtraDetails();

        $prompt = sprintf(
            <<<PROMPT
Rédige une annonce immobilière professionnelle, crédible et attractive.

Informations du bien :

- Type de bien : %s
- Ville : %s
- Code postal : %s
- Surface : %d m²
- Nombre de pièces : %d
- Parking : %s
- Détails supplémentaires : %s

Contraintes importantes :

- Ne jamais inventer une caractéristique absente des données fournies
- Utiliser uniquement les informations disponibles
- Ne pas mentionner de balcon, terrasse, vue, luminosité, cuisine équipée, transports ou prestations si cela n'est pas explicitement indiqué
- Style professionnel
- Ton premium mais crédible
- Texte fluide
- Maximum 120 mots
- Pas de liste à puces
- Mettre en avant les points forts réels du bien
- Finir par une phrase d'appel à contact

PROMPT,
            $property->getType(),
            $property->getCity(),
            $property->getPostalCode(),
            $property->getSurface(),
            $property->getRooms(),
            $property->hasParking() ? 'Oui' : 'Non',
            $extraDetails ?: 'Aucun détail supplémentaire'
        );

        return $this->aiClient->generateText($prompt);
    }
}