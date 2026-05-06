<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Service\OpenAiPropertyAdGenerator;
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
    ) {
    }

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
            $type = trim((string) $request->request->get('type', ''));
            $postalCode = preg_replace('/\D/', '', (string) $request->request->get('postalCode', ''));
            $city = trim((string) $request->request->get('city', ''));
            $surface = (int) $request->request->get('surface', 0);
            $rooms = (int) $request->request->get('rooms', 0);
            $parking = $request->request->getBoolean('parking');
            $extraDetails = trim((string) $request->request->get('extraDetails', ''));

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
                ->setExtraDetails($extraDetails !== '' ? $extraDetails : null)
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

    #[Route('/property/{id}/generate-ad', name: 'property_generate_ad', methods: ['POST'])]
    public function generateAd(
        Property $property,
        OpenAiPropertyAdGenerator $adGenerator
    ): Response {
        $this->denyAccessUnlessGranted('OWNER', $property);

        $user = $this->getUser();

        if (!$user instanceof User || !$user->isActive()) {
            return $this->redirectToRoute('pricing');
        }

        try {
            $property->setAdText($adGenerator->generate($property));
            $this->em->flush();

            $this->addFlash('success', 'Annonce IA générée.');
        } catch (\Throwable) {
            $this->addFlash('error', 'Impossible de générer l’annonce IA pour le moment.');
        }

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/pdf/{id}', name: 'property_pdf', methods: ['GET'])]
    public function pdf(Property $property): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $property);

        $user = $this->getUser();
        $logoDataUri = null;

        if ($user instanceof User && $user->getCompanyLogo()) {
            $logoPath = $this->getParameter('kernel.project_dir')
                . '/public/uploads/logos/'
                . $user->getCompanyLogo();

            if (is_file($logoPath)) {
                $mime = mime_content_type($logoPath);
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        }

        $html = $this->renderView('pdf/property.html.twig', [
            'property' => $property,
            'logoDataUri' => $logoDataUri,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
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

    #[Route('/delete-selected', name: 'property_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid(
            'bulk_delete_properties',
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $ids = $request->request->all('property_ids');

        if ($ids === []) {
            $this->addFlash('error', 'Aucune estimation sélectionnée.');

            return $this->redirectToRoute('dashboard');
        }

        $deletedCount = 0;

        foreach ($ids as $id) {
            $property = $this->em->getRepository(Property::class)->find((int) $id);

            if (!$property instanceof Property) {
                continue;
            }

            if ($property->getOwner() !== $user) {
                continue;
            }

            $this->em->remove($property);
            $deletedCount++;
        }

        $this->em->flush();

        $this->addFlash(
            'success',
            sprintf('%d estimation(s) supprimée(s).', $deletedCount)
        );

        return $this->redirectToRoute('dashboard');
    }
}