<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PricingController extends AbstractController
{
    #[Route('/pricing', name: 'pricing', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->getUser();

        if ($user instanceof User && !$user->isVerified()) {
            $this->addFlash('warning', 'Vérifiez votre email avant de choisir un abonnement.');

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('pricing/pricing.html.twig', [
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'] ?? '',
        ]);
    }
}