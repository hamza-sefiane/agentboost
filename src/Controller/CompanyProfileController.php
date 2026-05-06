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
    #[Route('/company', name: 'company_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $user->setCompanyName(trim((string) $request->request->get('company_name')));
            $user->setCompanyAddress(trim((string) $request->request->get('company_address')));
            $user->setCompanyPhone(trim((string) $request->request->get('company_phone')));

            $file = $request->files->get('company_logo');

            if ($file instanceof UploadedFile) {
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
                    $this->addFlash('error', 'Le logo doit être une image JPG, PNG ou WEBP.');

                    return $this->redirectToRoute('company_profile');
                }

                if ($file->getSize() > 2 * 1024 * 1024) {
                    $this->addFlash('error', 'Le logo ne doit pas dépasser 2 Mo.');

                    return $this->redirectToRoute('company_profile');
                }

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/logos';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                if ($user->getCompanyLogo()) {
                    $oldLogoPath = $uploadDir . '/' . $user->getCompanyLogo();

                    if (is_file($oldLogoPath)) {
                        (new Filesystem())->remove($oldLogoPath);
                    }
                }

                $filename = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();

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
}