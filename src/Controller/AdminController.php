<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $userCount = $this->em->getRepository(User::class)->count([]);

        $activeSubscriptions = $this->em->getRepository(User::class)->count([
            'subscriptionStatus' => User::STATUS_ACTIVE,
        ]);

        $propertyCount = $this->em->getRepository(Property::class)->count([]);

        $freeUsers = max(0, $userCount - $activeSubscriptions);
        $conversionRate = $userCount > 0 ? round(($activeSubscriptions / $userCount) * 100, 1) : 0;
        $estimatedRevenue = $activeSubscriptions * 29;

        $topCities = $this->connection->fetchAllAssociative('
            SELECT city, COUNT(*) AS total
            FROM property
            GROUP BY city
            ORDER BY total DESC
            LIMIT 5
        ');

        $latestUsers = $this->connection->fetchAllAssociative('
            SELECT id, email, subscription_status, current_plan
            FROM app_user
            ORDER BY id DESC
            LIMIT 8
        ');

        $latestStripeEvents = $this->connection->fetchAllAssociative('
            SELECT event_id, created_at
            FROM stripe_event
            ORDER BY created_at DESC
            LIMIT 8
        ');

        return $this->render('admin/stats.html.twig', [
            'stats' => [
                'users' => $userCount,
                'activeSubscriptions' => $activeSubscriptions,
                'freeUsers' => $freeUsers,
                'properties' => $propertyCount,
                'estimatedRevenue' => $estimatedRevenue,
                'conversionRate' => $conversionRate,
            ],
            'topCities' => $topCities,
            'latestUsers' => $latestUsers,
            'latestStripeEvents' => $latestStripeEvents,
        ]);
    }
}