<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\PropertyPhoto;
use App\Entity\User;
use App\Service\CloudinaryUploader;
use App\Service\OpenAiPropertyAdGenerator;
use App\Service\PropertyComparableGenerator;
use App\Service\PropertyEstimator;
use App\Service\PropertySellingAdviceGenerator;
use App\Service\SubscriptionLimiter;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
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
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SubscriptionLimiter $subscriptionLimiter,
        private readonly CloudinaryUploader $cloudinaryUploader,
    ) {}

    #[Route('', name: 'dashboard', methods: ['GET', 'POST'])]
    public function index(Request $request, PropertyEstimator $estimator): Response
    {
        $user = $this->getAuthenticatedUser();
        $this->denyAccessUnlessGranted('ACCESS_DASHBOARD');

        if ($request->isMethod('POST')) {
            if (!$this->subscriptionLimiter->canCreateEstimation($user)) {
                $this->addFlash('error', 'Vous avez atteint la limite gratuite de 3 estimations ce mois-ci. Passez à une offre premium pour continuer.');

                return $this->redirectToRoute('pricing');
            }

            $property = new Property();

            $response = $this->handlePropertyForm($property, $request, $estimator, $user);

            if ($response instanceof Response) {
                return $response;
            }

            $this->subscriptionLimiter->incrementEstimations($user);

            $this->em->persist($property);
            $this->em->flush();

            $this->addFlash('success', 'Estimation enregistrée.');

            return $this->redirectToRoute('dashboard');
        }

        $properties = $this->em->getRepository(Property::class)->findBy(
            ['owner' => $user],
            ['id' => 'DESC']
        );

        return $this->render('dashboard/index.html.twig', [
            'properties' => $properties,
            'usage' => [
                'isPremium' => $user->isActive(),
                'estimationsUsed' => $user->getMonthlyEstimations(),
                'estimationsLimit' => 3,
                'estimationsRemaining' => $this->subscriptionLimiter->getRemainingEstimations($user),
                'aiUsed' => $user->getMonthlyAiGenerations(),
                'aiLimit' => 1,
                'aiRemaining' => $this->subscriptionLimiter->getRemainingAiGenerations($user),
            ],
        ]);
    }

    #[Route('/property/{id}/edit', name: 'property_edit', methods: ['GET', 'POST'])]
    public function edit(Property $property, Request $request, PropertyEstimator $estimator): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $property);

        $user = $this->getAuthenticatedUser();

        if ($request->isMethod('POST')) {
            $response = $this->handlePropertyForm($property, $request, $estimator, $user, true);

            if ($response instanceof Response) {
                return $response;
            }

            $this->em->flush();

            $this->addFlash('success', 'Estimation modifiée.');

            return $this->redirectToRoute('dashboard');
        }

        return $this->render('dashboard/edit.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/property/{id}/generate-ad', name: 'property_generate_ad', methods: ['POST'])]
    public function generateAd(
        Property $property,
        Request $request,
        OpenAiPropertyAdGenerator $adGenerator,
    ): Response {
        $this->denyAccessUnlessGranted('OWNER', $property);

        if (!$this->isCsrfTokenValid('generate_ad_' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $user = $this->getAuthenticatedUser();

        if (!$this->subscriptionLimiter->canGenerateAd($user)) {
            $this->addFlash('error', 'Vous avez atteint la limite gratuite de génération IA ce mois-ci. Passez à une offre premium pour continuer.');

            return $this->redirectToRoute('pricing');
        }

        try {
            $property->setAdText(
                $adGenerator->generate($property, $request->getLocale())
            );

            $this->subscriptionLimiter->incrementAiGenerations($user);

            $this->em->flush();

            $this->addFlash('success', 'Annonce IA générée.');
        } catch (\Throwable) {
            $this->addFlash('error', 'Impossible de générer l’annonce IA pour le moment.');
        }

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/pdf/{id}', name: 'property_pdf', methods: ['GET'])]
    public function pdf(
        Property $property,
        Request $request,
        PropertySellingAdviceGenerator $adviceGenerator,
    ): Response {
        $this->denyAccessUnlessGranted('OWNER', $property);

        $user = $this->getAuthenticatedUser();
        $locale = $request->getLocale();

        $propertyUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('property_pdf', [
            'id' => $property->getId(),
        ]);

        $qrCodeResult = (new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $propertyUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 180,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        $qrCodeDataUri = $qrCodeResult->getDataUri();

        $html = $this->renderView('pdf/property.html.twig', [
            'property' => $property,
            'logoDataUri' => $this->getLogoDataUri($user),
            'photoDataUris' => $this->getPhotoDataUris($property),
            'sellingAdvices' => $adviceGenerator->generate($property, $locale),
            'sellingStrategy' => $adviceGenerator->generateSellingStrategy($property, $locale),
            'confidenceScore' => $adviceGenerator->generateConfidenceScore($property),
            'estimatedSaleDelay' => $adviceGenerator->generateEstimatedSaleDelay($property, $locale),
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('dpi', 120);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="estimation-agentboost.pdf"',
        ]);
    }

    #[Route('/pdf/{id}/premium', name: 'property_pdf_premium', methods: ['GET'])]
    public function premiumPdf(
        Property $property,
        Request $request,
        PropertySellingAdviceGenerator $adviceGenerator,
        PropertyComparableGenerator $comparableGenerator,
    ): Response {
        $this->denyAccessUnlessGranted('OWNER', $property);

        $user = $this->getAuthenticatedUser();
        $locale = $request->getLocale();

        $propertyUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('property_pdf', [
            'id' => $property->getId(),
        ]);

        $qrCodeResult = (new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $propertyUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 180,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        $qrCodeDataUri = $qrCodeResult->getDataUri();

        if ($user->getSubscriptionStatus() !== User::STATUS_ACTIVE || $user->getCurrentPlan() !== User::PLAN_YEARLY) {
            $this->addFlash('error', 'Le PDF premium est réservé aux abonnements annuels.');

            return $this->redirectToRoute('dashboard');
        }

        $html = $this->renderView('pdf/property_premium.html.twig', [
            'property' => $property,
            'logoDataUri' => $this->getLogoDataUri($user),
            'photoDataUris' => $this->getPhotoDataUris($property),
            'sellingAdvices' => $adviceGenerator->generate($property, $locale),
            'sellingStrategy' => $adviceGenerator->generateSellingStrategy($property, $locale),
            'confidenceScore' => $adviceGenerator->generateConfidenceScore($property),
            'estimatedSaleDelay' => $adviceGenerator->generateEstimatedSaleDelay($property, $locale),
            'qrCodeDataUri' => $qrCodeDataUri,
            'comparables' => $comparableGenerator->generate($property),
            'marketPosition' => $comparableGenerator->generateMarketPosition($property),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('dpi', 110);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="estimation-agentboost-premium.pdf"',
        ]);
    }

    #[Route('/property-photo/{id}/delete', name: 'property_photo_delete', methods: ['POST'])]
    public function deletePhoto(PropertyPhoto $photo, Request $request): Response
    {
        $property = $photo->getProperty();

        if (!$property instanceof Property) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('OWNER', $property);

        if (!$this->isCsrfTokenValid('delete_property_photo_' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->em->remove($photo);
        $this->em->flush();

        $this->addFlash('success', 'Photo supprimée.');

        return $this->redirectToRoute('property_edit', [
            'id' => $property->getId(),
        ]);
    }

    #[Route('/delete/{id}', name: 'property_delete', methods: ['POST'])]
    public function delete(Property $property, Request $request): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $property);

        if (!$this->isCsrfTokenValid('delete_property_' . $property->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->deletePropertyPhotos($property);

        $this->em->remove($property);
        $this->em->flush();

        $this->addFlash('success', 'Bien supprimé.');

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/delete-selected', name: 'property_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();

        if (!$this->isCsrfTokenValid('bulk_delete_properties', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $request->request->all('property_ids')),
            static fn(int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            $this->addFlash('error', 'Aucune estimation sélectionnée.');

            return $this->redirectToRoute('dashboard');
        }

        $properties = $this->em->getRepository(Property::class)->findBy([
            'id' => $ids,
            'owner' => $user,
        ]);

        $deletedCount = 0;

        foreach ($properties as $property) {
            $this->deletePropertyPhotos($property);
            $this->em->remove($property);
            $deletedCount++;
        }

        $this->em->flush();

        $this->addFlash('success', sprintf('%d estimation(s) supprimée(s).', $deletedCount));

        return $this->redirectToRoute('dashboard');
    }

    private function handlePropertyForm(
        Property $property,
        Request $request,
        PropertyEstimator $estimator,
        User $user,
        bool $isEdit = false,
    ): ?Response {
        $type = trim((string) $request->request->get('type', ''));
        $address = trim((string) $request->request->get('address', ''));
        $postalCode = preg_replace('/\D/', '', (string) $request->request->get('postalCode', '')) ?? '';
        $city = trim((string) $request->request->get('city', ''));
        $surface = max(0, (int) $request->request->get('surface', 0));
        $rooms = max(0, (int) $request->request->get('rooms', 0));
        $parking = $request->request->getBoolean('parking');
        $extraDetails = trim((string) $request->request->get('extraDetails', ''));

        if (mb_strtolower($type) === 'parking') {
            $rooms = 0;
            $parking = true;
        }

        $result = $estimator->estimate([
            'type' => $type,
            'address' => $address,
            'postalCode' => $postalCode,
            'city' => $city,
            'surface' => $surface,
            'rooms' => $rooms,
            'parking' => $parking,
        ]);

        if (
            !isset($result['estimate'], $result['lowEstimate'], $result['highEstimate'])
            || !is_numeric($result['estimate'])
            || !is_numeric($result['lowEstimate'])
            || !is_numeric($result['highEstimate'])
        ) {
            $this->addFlash('error', 'Données invalides.');

            return $this->redirectToFormOrigin($property, $isEdit);
        }

        $property
            ->setType($type)
            ->setAddress($address !== '' ? $address : null)
            ->setPostalCode($postalCode)
            ->setCity($city)
            ->setSurface($surface)
            ->setRooms($rooms)
            ->setParking($parking)
            ->setEstimate((int) $result['estimate'])
            ->setLowEstimate((int) $result['lowEstimate'])
            ->setHighEstimate((int) $result['highEstimate'])
            ->setExtraDetails($extraDetails !== '' ? $extraDetails : null)
            ->setOwner($user);

        if (!$isEdit) {
            $property->setAdText(isset($result['adText']) && is_string($result['adText']) ? $result['adText'] : null);
        }

        $photoResult = $this->attachUploadedPhotos($property, $request->files->all('photos'));

        foreach ($photoResult['warnings'] as $warning) {
            $this->addFlash('warning', $warning);
        }

        if ($photoResult['errors'] !== []) {
            foreach ($photoResult['errors'] as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToFormOrigin($property, $isEdit);
        }

        return null;
    }

    /**
     * @param array<int, mixed> $photos
     *
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    private function attachUploadedPhotos(Property $property, array $photos): array
    {
        $validPhotos = array_values(array_filter(
            $photos,
            static fn(mixed $photo): bool => $photo instanceof UploadedFile,
        ));

        if ($validPhotos === []) {
            return ['errors' => [], 'warnings' => []];
        }

        $remainingSlots = self::MAX_PHOTOS - $property->getPhotos()->count();

        if ($remainingSlots <= 0) {
            return [
                'errors' => [sprintf('Maximum %d photos autorisées par estimation.', self::MAX_PHOTOS)],
                'warnings' => [],
            ];
        }

        if (count($validPhotos) > $remainingSlots) {
            return [
                'errors' => [sprintf('Vous pouvez encore ajouter %d photo(s) maximum.', $remainingSlots)],
                'warnings' => [],
            ];
        }

        $errors = [];
        $position = $property->getPhotos()->count();

        foreach ($validPhotos as $photo) {
            $originalName = $photo->getClientOriginalName();

            if (!$photo->isValid()) {
                $errors[] = sprintf('Le fichier "%s" est invalide.', $originalName);
                continue;
            }

            $mimeType = $photo->getMimeType();

            if (!is_string($mimeType) || !array_key_exists($mimeType, self::ALLOWED_IMAGE_MIME_TYPES)) {
                $errors[] = sprintf('Le fichier "%s" doit être une image JPG, PNG ou WEBP.', $originalName);
                continue;
            }

            $size = $photo->getSize();

            if ($size !== null && $size > self::MAX_PHOTO_SIZE) {
                $errors[] = sprintf('Le fichier "%s" dépasse la taille maximale de 5 Mo.', $originalName);
                continue;
            }

            try {
                $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_IMAGE_MIME_TYPES[$mimeType];
                $uploadResult = $this->cloudinaryUploader->uploadPropertyPhoto($photo);
            } catch (\Throwable) {
                $errors[] = sprintf('Impossible d’enregistrer le fichier "%s".', $originalName);
                continue;
            }

            $propertyPhoto = (new PropertyPhoto())
                ->setFilename($filename)
                ->setCloudinaryUrl($uploadResult['url'])
                ->setCloudinaryPublicId($uploadResult['publicId'])
                ->setPosition($position++);

            $property->addPhoto($propertyPhoto);
        }

        return ['errors' => $errors, 'warnings' => []];
    }

    private function redirectToFormOrigin(Property $property, bool $isEdit): Response
    {
        return $this->redirectToRoute(
            $isEdit ? 'property_edit' : 'dashboard',
            $isEdit ? ['id' => $property->getId()] : [],
        );
    }

    private function deletePropertyPhotos(Property $property): void
    {
        foreach ($property->getPhotos() as $photo) {
            $this->em->remove($photo);
        }
    }

    private function getLogoDataUri(User $user): ?string
    {
        $logo = $user->getCompanyLogo();

        if (!$logo) {
            return null;
        }

        $path = $this->getProjectDir() . '/public/uploads/logos/' . $logo;

        if (!is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path);
        $content = file_get_contents($path);

        if (!is_string($mime) || $content === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    /**
     * @return array<int, string>
     */
    private function getPhotoDataUris(Property $property): array
    {
        $photoUrls = [];

        foreach ($property->getPhotos() as $photo) {
            $url = $photo->getCloudinaryUrl();

            if ($url) {
                $photoUrls[] = $url;
            }
        }

        return $photoUrls;
    }

    private function getProjectDir(): string
    {
        return (string) $this->getParameter('kernel.project_dir');
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
