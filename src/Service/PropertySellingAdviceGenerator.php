<?php

namespace App\Service;

use App\Entity\Property;

final class PropertySellingAdviceGenerator
{
    /**
     * @return array<int, string>
     */
    public function generate(Property $property): array
    {
        $type = mb_strtolower($property->getType());

        $advices = [
            'Préparer des photos lumineuses avec des pièces rangées et dégagées.',
            'Désencombrer les espaces principaux avant les visites.',
            'Soigner l’entrée et les premières impressions du bien.',
        ];

        if ($property->hasParking()) {
            $advices[] = 'Valoriser le stationnement dans l’annonce et lors des visites.';
        }

        if ($type === 'maison') {
            $advices[] = 'Préparer les extérieurs avant les prises de vue.';
        }

        if ($type === 'appartement') {
            $advices[] = 'Valoriser la circulation et la fonctionnalité des pièces.';
        }

        if ($property->getSurface() > 100) {
            $advices[] = 'Mettre en avant les volumes et la circulation des espaces.';
        }

        return array_slice(array_unique($advices), 0, 6);
    }

    /**
     * @return array<int, string>
     */
    public function generateSellingStrategy(Property $property): array
    {
        $type = mb_strtolower($property->getType());

        $strategy = [
            'Positionner le bien autour de l’estimation cible pour tester la demande.',
            'Prévoir une annonce courte, claire et orientée points forts réels.',
            'Mettre en avant les photos dès les premiers supports de diffusion.',
        ];

        if ($property->hasParking()) {
            $strategy[] = 'Utiliser le stationnement comme argument différenciant.';
        }

        if ($property->getSurface() > 100) {
            $strategy[] = 'Cibler les familles recherchant de grands volumes.';
        }

        if ($type === 'appartement') {
            $strategy[] = 'Valoriser la praticité et la facilité d’entretien.';
        }

        if ($type === 'maison') {
            $strategy[] = 'Insister sur le confort d’usage et l’espace disponible.';
        }

        return array_slice(array_unique($strategy), 0, 7);
    }

    public function generateConfidenceScore(Property $property): int
    {
        $score = 68;

        if ($property->getSurface() > 0) {
            $score += 8;
        }

        if ($property->getPostalCode() !== '') {
            $score += 8;
        }

        if ($property->getRooms() > 0 || mb_strtolower($property->getType()) === 'terrain') {
            $score += 6;
        }

        if ($property->getPhotos()->count() >= 3) {
            $score += 6;
        }

        if ($property->getExtraDetails()) {
            $score += 4;
        }

        return min(95, $score);
    }

    public function generateEstimatedSaleDelay(Property $property): string
    {
        $type = mb_strtolower($property->getType());
        $surface = $property->getSurface();

        if ($type === 'parking') {
            return '15 à 30 jours';
        }

        if ($surface <= 50) {
            return '30 à 45 jours';
        }

        if ($surface <= 120) {
            return '45 à 60 jours';
        }

        return '60 à 90 jours';
    }
}