<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Service\PropertyEstimator;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
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

        $this->em->refresh($user);
        $this->denyAccessUnlessGranted('ACCESS_DASHBOARD');

        if ($request->isMethod('POST')) {
            $type = (string) $request->request->get('type', '');
            $postalCode = preg_replace('/\D/', '', (string) $request->request->get('postalCode', ''));
            $city = (string) $request->request->get('city', '');
            $surface = (int) $request->request->get('surface', 0);
            $rooms = (int) $request->request->get('rooms', 0);
            $parking = $request->request->getBoolean('parking');

            if (strtolower($type) === 'parking') {
                $rooms = 0;
                $parking = true;
            }

            $data = [
                'type' => $type,
                'postalCode' => $postalCode,
                'city' => $city,
                'surface' => $surface,
                'rooms' => $rooms,
                'parking' => $parking,
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
                ->setParking($parking)
                ->setEstimate($result['estimate'])
                ->setAdText($result['adText'] ?? null)
                ->setOwner($user);

            $this->em->persist($property);
            $this->em->flush();

            $this->addFlash('success', 'Estimation enregistrée.');
            return $this->redirectToRoute('dashboard');
        }

        $typeFilter = (string) $request->query->get('type', '');
        $cityFilter = trim((string) $request->query->get('city', ''));
        $sort = (string) $request->query->get('sort', 'created_desc');

        $qb = $this->em->getRepository(Property::class)
            ->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $user);

        if ($typeFilter !== '') {
            $qb->andWhere('p.type = :type')
                ->setParameter('type', $typeFilter);
        }

        if ($cityFilter !== '') {
            $qb->andWhere('LOWER(p.city) LIKE :city')
                ->setParameter('city', '%' . strtolower($cityFilter) . '%');
        }

        match ($sort) {
            'estimate_asc' => $qb->orderBy('p.estimate', 'ASC'),
            'estimate_desc' => $qb->orderBy('p.estimate', 'DESC'),
            'surface_asc' => $qb->orderBy('p.surface', 'ASC'),
            'surface_desc' => $qb->orderBy('p.surface', 'DESC'),
            'city_asc' => $qb->orderBy('p.city', 'ASC'),
            'city_desc' => $qb->orderBy('p.city', 'DESC'),
            default => $qb->orderBy('p.id', 'DESC'),
        };

        return $this->render('dashboard/index.html.twig', [
            'properties' => $qb->getQuery()->getResult(),
            'filters' => [
                'type' => $typeFilter,
                'city' => $cityFilter,
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/pdf/{id}', name: 'property_pdf', methods: ['GET'])]
    public function pdf(Property $property): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $property);

        $html = $this->renderView('pdf/property.html.twig', [
            'property' => $property,
            'project_dir' => $this->getParameter('kernel.project_dir'),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="estimation-agentboost.pdf"',
            ]
        );
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