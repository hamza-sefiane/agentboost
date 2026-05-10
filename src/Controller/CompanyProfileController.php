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
        'image/jpeg',
        'image/png',
        'image/webp',
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

                $uploadDir = $this->getLogoUploadDir();

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    $this->addFlash('error', 'Impossible de préparer le dossier du logo.');

                    return $this->redirectToRoute('company_profile');
                }

                if ($user->getCompanyLogo()) {
                    $oldLogoPath = $uploadDir . DIRECTORY_SEPARATOR . $user->getCompanyLogo();

                    if (is_file($oldLogoPath)) {
                        (new Filesystem())->remove($oldLogoPath);
                    }
                }

                $extension = $file->guessExtension() ?: 'bin';
                $filename = bin2hex(random_bytes(16)) . '.' . $extension;

                $file->move($uploadDir, $filename);

                $user->setCompanyLogo($filename);
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
        if (!in_array($file->getMimeType(), self::ALLOWED_LOGO_MIME_TYPES, true)) {
            $this->addFlash('error', 'Le logo doit être une image JPG, PNG ou WEBP.');

            return $this->redirectToRoute('company_profile');
        }

        if ($file->getSize() !== false && $file->getSize() > self::MAX_LOGO_SIZE) {
            $this->addFlash('error', 'Le logo ne doit pas dépasser 2 Mo.');

            return $this->redirectToRoute('company_profile');
        }

        return null;
    }

    private function getLogoUploadDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/public/uploads/logos';
    }
}