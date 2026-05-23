<?php

namespace App\Service;

use App\Entity\Property;

final class PropertySellingAdviceGenerator
{
    /**
     * @return array<int, string>
     */
    public function generate(Property $property, string $locale = 'fr'): array
    {
        $type = mb_strtolower($property->getType());
        $surface = $property->getSurface();
        $details = mb_strtolower((string) $property->getExtraDetails());

        $advices = [
            $this->t(
                $locale,
                'Préparer des photos lumineuses, avec des pièces rangées et faciles à lire.',
                'Prepare bright photos with tidy, easy-to-read rooms.',
                'Preparar fotos luminosas con estancias ordenadas y fáciles de visualizar.'
            ),
            $this->t(
                $locale,
                'Désencombrer les espaces principaux pour renforcer la sensation de volume.',
                'Declutter the main rooms to enhance the feeling of space.',
                'Despejar los espacios principales para reforzar la sensación de amplitud.'
            ),
            $this->t(
                $locale,
                'Soigner l’entrée, la luminosité et les premières impressions avant chaque visite.',
                'Pay attention to the entrance, brightness and first impressions before each visit.',
                'Cuidar la entrada, la luminosidad y las primeras impresiones antes de cada visita.'
            ),
        ];

        if ($property->hasParking()) {
            $advices[] = $this->t(
                $locale,
                'Mettre le stationnement en avant dès l’annonce, surtout dans les zones où il fait la différence.',
                'Highlight the parking space in the listing, especially in areas where it makes a difference.',
                'Destacar la plaza de aparcamiento en el anuncio, especialmente en zonas donde marca la diferencia.'
            );
        }

        if ($type === 'maison' || $type === 'house' || $type === 'casa') {
            $advices[] = $this->t(
                $locale,
                'Nettoyer les extérieurs et valoriser les accès avant les prises de vue.',
                'Clean the outdoor areas and highlight access points before taking photos.',
                'Limpiar los exteriores y resaltar los accesos antes de tomar las fotos.'
            );
        }

        if ($type === 'appartement' || $type === 'apartment' || $type === 'apartamento') {
            $advices[] = $this->t(
                $locale,
                'Mettre en avant la distribution des pièces et la simplicité d’usage au quotidien.',
                'Highlight the room layout and everyday practicality.',
                'Destacar la distribución de las estancias y la facilidad de uso diario.'
            );
        }

        if ($type === 'terrain' || $type === 'land' || $type === 'terreno') {
            $advices[] = $this->t(
                $locale,
                'Présenter clairement la surface, l’accès et le potentiel d’usage du terrain.',
                'Clearly present the surface area, access and potential use of the land.',
                'Presentar claramente la superficie, el acceso y el potencial de uso del terreno.'
            );
        }

        if ($type === 'parking') {
            $advices[] = $this->t(
                $locale,
                'Préciser les conditions d’accès, la sécurité et la facilité de manœuvre.',
                'Specify access conditions, security and ease of maneuvering.',
                'Precisar las condiciones de acceso, la seguridad y la facilidad de maniobra.'
            );
        }

        if ($surface >= 90) {
            $advices[] = $this->t(
                $locale,
                'Valoriser les volumes, la circulation intérieure et les possibilités d’aménagement.',
                'Highlight the volumes, interior flow and layout possibilities.',
                'Poner en valor los volúmenes, la circulación interior y las posibilidades de distribución.'
            );
        }

        if (str_contains($details, 'balcon') || str_contains($details, 'terrasse') || str_contains($details, 'terrace') || str_contains($details, 'balcony') || str_contains($details, 'terraza')) {
            $advices[] = $this->t(
                $locale,
                'Soigner la présentation de l’espace extérieur, même s’il est de petite taille.',
                'Carefully present the outdoor space, even if it is small.',
                'Cuidar la presentación del espacio exterior, aunque sea pequeño.'
            );
        }

        if (str_contains($details, 'gare') || str_contains($details, 'transport') || str_contains($details, 'station') || str_contains($details, 'transporte')) {
            $advices[] = $this->t(
                $locale,
                'Mentionner clairement la proximité des transports dans les supports de diffusion.',
                'Clearly mention proximity to transport in the marketing materials.',
                'Mencionar claramente la proximidad del transporte en los materiales de difusión.'
            );
        }

        if (
            str_contains($details, 'commerce')
            || str_contains($details, 'commerces')
            || str_contains($details, 'école')
            || str_contains($details, 'ecole')
            || str_contains($details, 'shop')
            || str_contains($details, 'shops')
            || str_contains($details, 'school')
            || str_contains($details, 'tienda')
            || str_contains($details, 'tiendas')
            || str_contains($details, 'escuela')
        ) {
            $advices[] = $this->t(
                $locale,
                'Mettre en avant les services de proximité utiles pour les acquéreurs.',
                'Highlight nearby services that are useful to buyers.',
                'Destacar los servicios cercanos útiles para los compradores.'
            );
        }

        return array_slice(array_values(array_unique($advices)), 0, 6);
    }

    /**
     * @return array<int, string>
     */
    public function generateSellingStrategy(Property $property, string $locale = 'fr'): array
    {
        $type = mb_strtolower($property->getType());
        $surface = $property->getSurface();
        $estimate = $property->getEstimate();
        $lowEstimate = $property->getLowEstimate();
        $highEstimate = $property->getHighEstimate();
        $details = mb_strtolower((string) $property->getExtraDetails());

        $strategy = [
            $this->t(
                $locale,
                'Démarrer avec un prix d’affichage cohérent avec la fourchette haute, sans sortir du marché.',
                'Start with a listing price aligned with the high range without exceeding the market.',
                'Empezar con un precio de publicación coherente con el rango alto, sin salirse del mercado.'
            ),
            $this->t(
                $locale,
                'Tester les premiers retours acheteurs sur les 10 à 15 premiers jours de diffusion.',
                'Monitor buyer feedback during the first 10 to 15 days of publication.',
                'Evaluar las primeras reacciones de los compradores durante los primeros 10 a 15 días de publicación.'
            ),
            $this->t(
                $locale,
                'Utiliser les photos comme premier levier d’attractivité avant de multiplier les canaux.',
                'Use photos as the first driver of attractiveness before expanding distribution channels.',
                'Utilizar las fotos como primer factor de atracción antes de multiplicar los canales.'
            ),
        ];

        if ($estimate !== null && $lowEstimate !== null && $highEstimate !== null) {
            $spread = $highEstimate - $lowEstimate;

            if ($spread <= max(15000, (int) ($estimate * 0.06))) {
                $strategy[] = $this->t(
                    $locale,
                    'La fourchette étant resserrée, privilégier un positionnement ferme et lisible.',
                    'Because the range is narrow, prefer a firm and clear price positioning.',
                    'Dado que el rango es ajustado, conviene priorizar un posicionamiento firme y claro.'
                );
            } else {
                $strategy[] = $this->t(
                    $locale,
                    'La fourchette étant large, surveiller rapidement les retours pour ajuster le prix si nécessaire.',
                    'Because the range is wide, monitor feedback quickly to adjust the price if needed.',
                    'Dado que el rango es amplio, conviene vigilar rápidamente las reacciones para ajustar el precio si es necesario.'
                );
            }
        }

        if ($property->hasParking()) {
            $strategy[] = $this->t(
                $locale,
                'Présenter le stationnement comme un avantage concret, pas comme une simple option.',
                'Present parking as a concrete advantage, not just as an option.',
                'Presentar el aparcamiento como una ventaja concreta, no como una simple opción.'
            );
        }

        if ($surface >= 90) {
            $strategy[] = $this->t(
                $locale,
                'Cibler en priorité les acquéreurs recherchant de l’espace et une distribution confortable.',
                'Focus first on buyers looking for space and a comfortable layout.',
                'Dirigirse prioritariamente a compradores que buscan espacio y una distribución cómoda.'
            );
        }

        if ($type === 'appartement' || $type === 'apartment' || $type === 'apartamento') {
            $strategy[] = $this->t(
                $locale,
                'Mettre l’accent sur la fonctionnalité, les charges perçues et la vie quotidienne dans la résidence.',
                'Emphasize practicality, perceived costs and daily life in the residence.',
                'Poner el énfasis en la funcionalidad, los gastos percibidos y la vida diaria en la residencia.'
            );
        }

        if ($type === 'maison' || $type === 'house' || $type === 'casa') {
            $strategy[] = $this->t(
                $locale,
                'Valoriser l’usage familial, les extérieurs et la capacité d’évolution du bien.',
                'Highlight family use, outdoor areas and the property’s potential for evolution.',
                'Poner en valor el uso familiar, los exteriores y la capacidad de evolución del inmueble.'
            );
        }

        if ($type === 'terrain' || $type === 'land' || $type === 'terreno') {
            $strategy[] = $this->t(
                $locale,
                'Présenter le potentiel du terrain avec prudence, sans promettre de faisabilité non vérifiée.',
                'Present the land’s potential carefully without promising unverified feasibility.',
                'Presentar el potencial del terreno con prudencia, sin prometer una viabilidad no verificada.'
            );
        }

        if ($type === 'parking') {
            $strategy[] = $this->t(
                $locale,
                'Diffuser en priorité auprès d’acheteurs locaux ou d’investisseurs cherchant un actif simple.',
                'Target local buyers or investors looking for a simple asset first.',
                'Difundir primero entre compradores locales o inversores que buscan un activo simple.'
            );
        }

        if (str_contains($details, 'travaux') || str_contains($details, 'renovation') || str_contains($details, 'renovación')) {
            $strategy[] = $this->t(
                $locale,
                'Anticiper les questions sur les travaux avec une présentation claire et transparente.',
                'Anticipate questions about renovation work with a clear and transparent presentation.',
                'Anticipar las preguntas sobre las obras con una presentación clara y transparente.'
            );
        }

        if (str_contains($details, 'centre-ville') || str_contains($details, 'centre ville') || str_contains($details, 'city center') || str_contains($details, 'centro')) {
            $strategy[] = $this->t(
                $locale,
                'Mettre en avant la proximité du centre-ville dans le titre ou les premières lignes de l’annonce.',
                'Highlight proximity to the city center in the title or opening lines of the listing.',
                'Destacar la proximidad del centro en el título o en las primeras líneas del anuncio.'
            );
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

    public function generateEstimatedSaleDelay(Property $property, string $locale = 'fr'): string
    {
        $type = mb_strtolower($property->getType());
        $surface = $property->getSurface();
        $hasDetails = $property->getExtraDetails() !== null;
        $hasPhotos = $property->getPhotos()->count() >= 3;

        if ($type === 'parking') {
            return $this->t($locale, '15 à 30 jours', '15 to 30 days', '15 a 30 días');
        }

        if ($type === 'terrain' || $type === 'land' || $type === 'terreno') {
            return $this->t($locale, '60 à 120 jours', '60 to 120 days', '60 a 120 días');
        }

        if ($surface <= 45) {
            return $hasPhotos && $hasDetails
                ? $this->t($locale, '25 à 40 jours', '25 to 40 days', '25 a 40 días')
                : $this->t($locale, '30 à 45 jours', '30 to 45 days', '30 a 45 días');
        }

        if ($surface <= 90) {
            return $hasPhotos && $hasDetails
                ? $this->t($locale, '35 à 55 jours', '35 to 55 days', '35 a 55 días')
                : $this->t($locale, '45 à 60 jours', '45 to 60 days', '45 a 60 días');
        }

        if ($surface <= 130) {
            return $hasPhotos && $hasDetails
                ? $this->t($locale, '45 à 70 jours', '45 to 70 days', '45 a 70 días')
                : $this->t($locale, '60 à 90 jours', '60 to 90 days', '60 a 90 días');
        }

        return $this->t($locale, '75 à 120 jours', '75 to 120 days', '75 a 120 días');
    }

    private function t(string $locale, string $fr, string $en, string $es): string
    {
        return match ($locale) {
            'en' => $en,
            'es' => $es,
            default => $fr,
        };
    }
}
