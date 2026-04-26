<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
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
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User || !$user->isActive()) {
            return $this->redirectToRoute('subscribe');
        }

        $postalCode = preg_replace('/\D/', '', (string) $request->request->get('postalCode'));

        if (strlen($postalCode) !== 5) {
            $this->addFlash('error', 'Le code postal doit contenir exactement 5 chiffres.');

            return $this->redirectToRoute('dashboard');
        }

        $propertyData = [
            'type' => (string) $request->request->get('type'),
            'postalCode' => $postalCode,
            'city' => (string) $request->request->get('city'),
            'surface' => (int) $request->request->get('surface'),
            'rooms' => (int) $request->request->get('rooms'),
            'parking' => $request->request->getBoolean('parking'),
        ];

        $result = $estimator->estimate($propertyData);

        $property = new Property();
        $property
            ->setType($propertyData['type'])
            ->setPostalCode($propertyData['postalCode'])
            ->setCity($propertyData['city'])
            ->setSurface($propertyData['surface'])
            ->setRooms($propertyData['rooms'])
            ->setParking($propertyData['parking'])
            ->setEstimate($result['estimate'] ?? 0)
            ->setAdText($result['adText'] ?? '')
            ->setOwner($user);

        $em->persist($property);
        $em->flush();

        $this->addFlash('success', 'Estimation enregistrée.');

        return $this->redirectToRoute('dashboard');
    }
}