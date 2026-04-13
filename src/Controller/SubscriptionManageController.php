<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SubscriptionManageController extends AbstractController
{
    #[Route(
        '/account/subscription',
        name: 'subscription_manage',
        methods: ['GET']
    )]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('subscription/manage.html.twig', [
            'user' => $user,
        ]);
    }
}
