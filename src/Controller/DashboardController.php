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

#[Route('/dashboard')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    #[Route('', name: 'dashboard', methods: ['GET', 'POST'])]
    public function index(Request $request, PropertyEstimator $estimator): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // 🔑 CRUCIAL : on recharge l’utilisateur depuis la DB
        $this->em->refresh($user);

        // 🔐 CONTRÔLE D’ACCÈS AU BON MOMENT
        $this->denyAccessUnlessGranted('ACCESS_DASHBOARD');

        // ----------------------------------------
        // À PARTIR D’ICI, L’ACCÈS EST GARANTI
        // ----------------------------------------

        if ($request->isMethod('POST')) {
            $type = (string) $request->request->get('type', '');
            $postalCode = (string) $request->request->get('postalCode', '');
             $city = (string) $request->request->get('city', '');
            $surface = (int) $request->request->get('surface', 0);
            $rooms = (int) $request->request->get('rooms', 0);

            $data = [
                'type' => $type,
                'postalCode' => $postalCode,
                'city' => $city,
                'surface' => $surface,
                'rooms' => $rooms,
            ];

            $result = $estimator->estimate($data);

            if (!isset($result['estimate']) || $result['estimate'] === null) {
                $this->addFlash('error', 'Données invalides.');
                return $this->redirectToRoute('dashboard');
            }

            $property = (new Property())
                ->setType($type)
                ->setPostalCode($postalCode)
                ->setCity($city)
                ->setSurface($surface)
                ->setRooms($rooms)
                ->setEstimate($result['estimate'])
                ->setAdText($result['adText'] ?? null)
                ->setOwner($user);

            $this->em->persist($property);
            $this->em->flush();

            $this->addFlash('success', 'Estimation enregistrée.');
            return $this->redirectToRoute('dashboard');
        }

        $properties = $this->em->getRepository(Property::class)
            ->findBy(['owner' => $user], ['id' => 'DESC']);

        return $this->render('dashboard/index.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/delete/{id}', name: 'property_delete', methods: ['POST'])]
    public function delete(Property $property): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $property);

        $this->em->remove($property);
        $this->em->flush();

        $this->addFlash('success', 'Bien supprimé.');
        return $this->redirectToRoute('dashboard');
    }
}
