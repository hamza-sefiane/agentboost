<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\PropertyPhoto;
use App\Entity\User;
use App\Service\OpenAiPropertyAdGenerator;
use App\Service\PropertyEstimator;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
final class DashboardController extends AbstractController
{
    private const MAX_PHOTOS = 5;
    private const MAX_PHOTO_SIZE = 5 * 1024 * 1024;

    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

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

            $photos = $request->files->all('photos');
            $photoErrors = $this->attachUploadedPhotos($property, $photos);

            if ($photoErrors !== []) {
                foreach ($photoErrors as $photoError) {
                    $this->addFlash('error', $photoError);
                }

                return $this->redirectToRoute('dashboard');
            }

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

        $photoDataUris = $this->getPhotoDataUris($property);

        $html = $this->renderView('pdf/property.html.twig', [
            'property' => $property,
            'logoDataUri' => $logoDataUri,
            'photoDataUris' => $photoDataUris,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

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

        $this->deletePhysicalPhotos($property);

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

            $this->deletePhysicalPhotos($property);

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

    /**
     * @param array<int, UploadedFile|null> $photos
     *
     * @return array<int, string>
     */
    private function attachUploadedPhotos(Property $property, array $photos): array
    {
        $validPhotos = array_values(array_filter(
            $photos,
            static fn ($photo): bool => $photo instanceof UploadedFile
        ));

        if (count($validPhotos) > self::MAX_PHOTOS) {
            return [sprintf('Maximum %d photos autorisées.', self::MAX_PHOTOS)];
        }

        $uploadDir = $this->getPropertyUploadDir();

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $errors = [];
        $position = 0;

        foreach ($validPhotos as $photo) {
            if (!$photo->isValid()) {
                $errors[] = sprintf('Le fichier "%s" est invalide.', $photo->getClientOriginalName());
                continue;
            }

            if (!in_array($photo->getMimeType(), self::ALLOWED_IMAGE_MIME_TYPES, true)) {
                $errors[] = sprintf(
                    'Le fichier "%s" doit être une image JPG, PNG ou WEBP.',
                    $photo->getClientOriginalName()
                );
                continue;
            }

            if ($photo->getSize() !== false && $photo->getSize() > self::MAX_PHOTO_SIZE) {
                $errors[] = sprintf(
                    'Le fichier "%s" dépasse la taille maximale de 5 Mo.',
                    $photo->getClientOriginalName()
                );
                continue;
            }

            $extension = $photo->guessExtension() ?: 'jpg';
            $filename = uniqid('property_', true) . '.' . $extension;

            $photo->move($uploadDir, $filename);

            $propertyPhoto = (new PropertyPhoto())
                ->setFilename($filename)
                ->setPosition($position++);

            $property->addPhoto($propertyPhoto);
        }

        return $errors;
    }

    private function deletePhysicalPhotos(Property $property): void
    {
        $uploadDir = $this->getPropertyUploadDir();

        foreach ($property->getPhotos() as $photo) {
            $path = $uploadDir . DIRECTORY_SEPARATOR . $photo->getFilename();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function getPhotoDataUris(Property $property): array
    {
        $uploadDir = $this->getPropertyUploadDir();
        $dataUris = [];

        foreach ($property->getPhotos() as $photo) {
            $path = $uploadDir . DIRECTORY_SEPARATOR . $photo->getFilename();

            if (!is_file($path)) {
                continue;
            }

            $mime = mime_content_type($path);

            if (!is_string($mime) || !in_array($mime, self::ALLOWED_IMAGE_MIME_TYPES, true)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            $dataUris[] = 'data:' . $mime . ';base64,' . base64_encode($content);
        }

        return $dataUris;
    }

    private function getPropertyUploadDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/public/uploads/properties';
    }
}