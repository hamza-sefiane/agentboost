<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyProfileController extends AbstractController
{
    private const MAX_LOGO_SIZE = 2 * 1024 * 1024;

    private const ALLOWED_LOGO_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    #[Route('/company', name: 'company_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $user
                ->setCompanyName((string) $request->request->get('company_name'))
                ->setCompanyPhone((string) $request->request->get('company_phone'))
                ->setAgencyStreet((string) $request->request->get('agency_street'))
                ->setAgencyAddressComplement((string) $request->request->get('agency_address_complement'))
                ->setAgencyPostalCode((string) $request->request->get('agency_postal_code'))
                ->setAgencyCity((string) $request->request->get('agency_city'))
                ->setAgencyEmail((string) $request->request->get('agency_email'))
                ->setAgencyWebsite((string) $request->request->get('agency_website'));

            $legacyAddress = trim(sprintf(
                "%s\n%s\n%s %s",
                $user->getAgencyStreet() ?? '',
                $user->getAgencyAddressComplement() ?? '',
                $user->getAgencyPostalCode() ?? '',
                $user->getAgencyCity() ?? ''
            ));

            $user->setCompanyAddress($legacyAddress !== '' ? $legacyAddress : null);

            $file = $request->files->get('company_logo');

            if ($file instanceof UploadedFile) {
                $errorResponse = $this->validateLogo($file);

                if ($errorResponse instanceof Response) {
                    return $errorResponse;
                }

                if ($this->isVercelRuntime()) {
                    $this->addFlash('warning', 'Logo ignoré sur Vercel sans stockage externe. Les informations agence ont été enregistrées.');
                } else {
                    $logoSaved = $this->saveLogo($user, $file);

                    if (!$logoSaved) {
                        $this->addFlash('warning', 'Impossible d’enregistrer le logo. Les informations agence ont été enregistrées.');
                    }
                }
            }

            $em->flush();

            $this->addFlash('success', 'Informations agence mises à jour.');

            return $this->redirectToRoute('company_profile');
        }

        return $this->render('company/profile.html.twig', [
            'user' => $user,
        ]);
    }

    private function validateLogo(UploadedFile $file): ?Response
    {
        $mimeType = $file->getMimeType();

        if (!is_string($mimeType) || !array_key_exists($mimeType, self::ALLOWED_LOGO_MIME_TYPES)) {
            $this->addFlash('error', 'Le logo doit être une image JPG, PNG ou WEBP.');

            return $this->redirectToRoute('company_profile');
        }

        $size = $file->getSize();

        if ($size !== null && $size > self::MAX_LOGO_SIZE) {
            $this->addFlash('error', 'Le logo ne doit pas dépasser 2 Mo.');

            return $this->redirectToRoute('company_profile');
        }

        return null;
    }

    private function saveLogo(User $user, UploadedFile $file): bool
    {
        $uploadDir = $this->getLogoUploadDir();

        if (!$this->ensureWritableDirectory($uploadDir)) {
            return false;
        }

        $mimeType = $file->getMimeType();

        if (!is_string($mimeType) || !array_key_exists($mimeType, self::ALLOWED_LOGO_MIME_TYPES)) {
            return false;
        }

        try {
            if ($user->getCompanyLogo()) {
                $oldLogoPath = $uploadDir . DIRECTORY_SEPARATOR . $user->getCompanyLogo();

                if (is_file($oldLogoPath)) {
                    (new Filesystem())->remove($oldLogoPath);
                }
            }

            $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_LOGO_MIME_TYPES[$mimeType];

            $file->move($uploadDir, $filename);

            $user->setCompanyLogo($filename);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureWritableDirectory(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        return is_writable($dir);
    }

    private function getLogoUploadDir(): string
    {
        return $this->getProjectDir() . '/public/uploads/logos';
    }

    private function getProjectDir(): string
    {
        return (string) $this->getParameter('kernel.project_dir');
    }

    private function isVercelRuntime(): bool
    {
        return getenv('VERCEL') === '1' || getenv('VERCEL_ENV') !== false;
    }
}