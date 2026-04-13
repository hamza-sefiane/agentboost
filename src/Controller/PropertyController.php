<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Property;
use App\Service\PropertyEstimator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PropertyController extends AbstractController
{
    #[Route('/property/estimate', name: 'estimate_property', methods: ['POST'])]
    public function estimate(
        Request $request,
        PropertyEstimator $estimator,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user || !$user->isActive()) {
            return $this->redirectToRoute('subscribe'); // Redirige vers la souscription si pas actif
        }

        // Récupération des données du formulaire
        $propertyData = [
            'type' => $request->request->get('type'),
            'city' => $request->request->get('city'),
            'surface' => (float) $request->request->get('surface'),
            'rooms' => (int) $request->request->get('rooms'),
        ];

        // Estimation via le service PropertyEstimator
        $result = $estimator->estimate($propertyData);

        // Création et sauvegarde de la propriété
        $property = new Property();
        $property->setType($propertyData['type'])
                 ->setCity($propertyData['city'])
                 ->setSurface($propertyData['surface'])
                 ->setRooms($propertyData['rooms'])
                 ->setEstimate($result['estimate'] ?? 0)
                 ->setAdText($result['adText'] ?? '')
                 ->setOwner($user);

        $em->persist($property);
        $em->flush();

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'estimate' => $result['estimate'] ?? null,
            'adText' => $result['adText'] ?? '',
            'isActive' => $user->isActive(),
        ]);
    }
}
