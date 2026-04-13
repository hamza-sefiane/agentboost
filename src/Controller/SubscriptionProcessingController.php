<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SubscriptionProcessingController extends AbstractController
{
    #[Route('/subscription/processing', name: 'subscription_processing', methods: ['GET'])]
    public function __invoke(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user && $user->isActive()) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('subscription/processing.html.twig');
    }
}
