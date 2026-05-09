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
        $surface = $property->getSurface();
        $details = mb_strtolower((string) $property->getExtraDetails());

        $advices = [
            'Préparer des photos lumineuses, avec des pièces rangées et faciles à lire.',
            'Désencombrer les espaces principaux pour renforcer la sensation de volume.',
            'Soigner l’entrée, la luminosité et les premières impressions avant chaque visite.',
        ];

        if ($property->hasParking()) {
            $advices[] = 'Mettre le stationnement en avant dès l’annonce, surtout dans les zones où il fait la différence.';
        }

        if ($type === 'maison') {
            $advices[] = 'Nettoyer les extérieurs et valoriser les accès avant les prises de vue.';
        }

        if ($type === 'appartement') {
            $advices[] = 'Mettre en avant la distribution des pièces et la simplicité d’usage au quotidien.';
        }

        if ($type === 'terrain') {
            $advices[] = 'Présenter clairement la surface, l’accès et le potentiel d’usage du terrain.';
        }

        if ($type === 'parking') {
            $advices[] = 'Préciser les conditions d’accès, la sécurité et la facilité de manœuvre.';
        }

        if ($surface >= 90) {
            $advices[] = 'Valoriser les volumes, la circulation intérieure et les possibilités d’aménagement.';
        }

        if (str_contains($details, 'balcon') || str_contains($details, 'terrasse')) {
            $advices[] = 'Soigner la présentation de l’espace extérieur, même s’il est de petite taille.';
        }

        if (str_contains($details, 'gare') || str_contains($details, 'transport')) {
            $advices[] = 'Mentionner clairement la proximité des transports dans les supports de diffusion.';
        }

        if (str_contains($details, 'commerce') || str_contains($details, 'école') || str_contains($details, 'ecole')) {
            $advices[] = 'Mettre en avant les services de proximité utiles pour les acquéreurs.';
        }

        return array_slice(array_values(array_unique($advices)), 0, 6);
    }

    /**
     * @return array<int, string>
     */
    public function generateSellingStrategy(Property $property): array
    {
        $type = mb_strtolower($property->getType());
        $surface = $property->getSurface();
        $estimate = $property->getEstimate();
        $lowEstimate = $property->getLowEstimate();
        $highEstimate = $property->getHighEstimate();
        $details = mb_strtolower((string) $property->getExtraDetails());

        $strategy = [
            'Démarrer avec un prix d’affichage cohérent avec la fourchette haute, sans sortir du marché.',
            'Tester les premiers retours acheteurs sur les 10 à 15 premiers jours de diffusion.',
            'Utiliser les photos comme premier levier d’attractivité avant de multiplier les canaux.',
        ];

        if ($estimate !== null && $lowEstimate !== null && $highEstimate !== null) {
            $spread = $highEstimate - $lowEstimate;

            if ($spread <= max(15000, (int) ($estimate * 0.06))) {
                $strategy[] = 'La fourchette étant resserrée, privilégier un positionnement ferme et lisible.';
            } else {
                $strategy[] = 'La fourchette étant large, surveiller rapidement les retours pour ajuster le prix si nécessaire.';
            }
        }

        if ($property->hasParking()) {
            $strategy[] = 'Présenter le stationnement comme un avantage concret, pas comme une simple option.';
        }

        if ($surface >= 90) {
            $strategy[] = 'Cibler en priorité les acquéreurs recherchant de l’espace et une distribution confortable.';
        }

        if ($type === 'appartement') {
            $strategy[] = 'Mettre l’accent sur la fonctionnalité, les charges perçues et la vie quotidienne dans la résidence.';
        }

        if ($type === 'maison') {
            $strategy[] = 'Valoriser l’usage familial, les extérieurs et la capacité d’évolution du bien.';
        }

        if ($type === 'terrain') {
            $strategy[] = 'Présenter le potentiel du terrain avec prudence, sans promettre de faisabilité non vérifiée.';
        }

        if ($type === 'parking') {
            $strategy[] = 'Diffuser en priorité auprès d’acheteurs locaux ou d’investisseurs cherchant un actif simple.';
        }

        if (str_contains($details, 'travaux')) {
            $strategy[] = 'Anticiper les questions sur les travaux avec une présentation claire et transparente.';
        }

        if (str_contains($details, 'centre-ville') || str_contains($details, 'centre ville')) {
            $strategy[] = 'Mettre en avant la proximité du centre-ville dans le titre ou les premières lignes de l’annonce.';
        }

        return array_slice(array_values(array_unique($strategy)), 0, 7);
    }

    public function generateConfidenceScore(Property $property): int
    {
        $score = 62;

        if ($property->getAddress()) {
            $score += 7;
        }

        if ($property->getPostalCode() !== '' && $property->getCity() !== '') {
            $score += 8;
        }

        if ($property->getSurface() > 0) {
            $score += 8;
        }

        if ($property->getRooms() > 0 || in_array(mb_strtolower($property->getType()), ['terrain', 'parking'], true)) {
            $score += 6;
        }

        if ($property->getPhotos()->count() >= 3) {
            $score += 6;
        }

        if ($property->getPhotos()->count() >= 5) {
            $score += 3;
        }

        if ($property->getExtraDetails()) {
            $score += 5;
        }

        if ($property->hasParking()) {
            $score += 3;
        }

        return min(95, $score);
    }

    public function generateEstimatedSaleDelay(Property $property): string
    {
        $type = mb_strtolower($property->getType());
        $surface = $property->getSurface();
        $hasDetails = $property->getExtraDetails() !== null;
        $hasPhotos = $property->getPhotos()->count() >= 3;

        if ($type === 'parking') {
            return '15 à 30 jours';
        }

        if ($type === 'terrain') {
            return '60 à 120 jours';
        }

        if ($surface <= 45) {
            return $hasPhotos && $hasDetails ? '25 à 40 jours' : '30 à 45 jours';
        }

        if ($surface <= 90) {
            return $hasPhotos && $hasDetails ? '35 à 55 jours' : '45 à 60 jours';
        }

        if ($surface <= 130) {
            return $hasPhotos && $hasDetails ? '45 à 70 jours' : '60 à 90 jours';
        }

        return '75 à 120 jours';
    }
}