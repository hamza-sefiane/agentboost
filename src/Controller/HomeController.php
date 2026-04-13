<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }


     #[Route('/subscribe/success', name: 'subscribe_success', methods: ['GET'])]
    public function subscribeSuccess(): Response
    {
        return $this->render('subscription/success.html.twig');
    }

    #[Route('/subscribe/cancel', name: 'subscribe_cancel', methods: ['GET'])]
    public function subscribeCancel(): Response
    {
        return $this->render('subscription/cancel.html.twig');
    }
}
