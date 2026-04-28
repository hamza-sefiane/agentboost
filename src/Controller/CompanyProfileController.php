<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompanyProfileController extends AbstractController
{
    #[Route('/company', name: 'company_profile')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {

            $user->setCompanyName($request->request->get('company_name'));
            $user->setCompanyAddress($request->request->get('company_address'));
            $user->setCompanyPhone($request->request->get('company_phone'));

            // Upload logo
            $file = $request->files->get('company_logo');

            if ($file) {
                $filename = uniqid() . '.' . $file->guessExtension();

                $file->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/logos',
                    $filename
                );

                $user->setCompanyLogo($filename);
            }

            $em->flush();

            $this->addFlash('success', 'Profil entreprise mis à jour');
            return $this->redirectToRoute('company_profile');
        }

        return $this->render('company/profile.html.twig', [
            'user' => $user
        ]);
    }
}