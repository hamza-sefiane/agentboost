<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoodbyeController extends AbstractController
{
    #[Route('/goodbye', name: 'goodbye', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('security/goodbye.html.twig');
    }
}
