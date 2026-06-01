<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    private const OWNER_ADMIN_EMAIL = 'hamza95340@hotmail.fr';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {}

    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $this->denyUnlessOwnerAdmin();

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

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request): Response
    {
        $this->denyUnlessOwnerAdmin();

        $search = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $whereParts = [];
        $params = [];

        if ($search !== '') {
            $whereParts[] = 'email LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        if (in_array($status, [
            User::STATUS_ACTIVE,
            User::STATUS_INACTIVE,
            User::STATUS_GRACE,
        ], true)) {
            $whereParts[] = 'subscription_status = :status';
            $params['status'] = $status;
        }

        $where = $whereParts !== []
            ? 'WHERE ' . implode(' AND ', $whereParts)
            : '';

        $totalUsers = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM app_user {$where}",
            $params
        );

        $users = $this->connection->fetchAllAssociative(
            "
            SELECT
                id,
                email,
                subscription_status,
                current_plan,
                is_verified,
                is_active,
                next_billing_date,
                created_at
            FROM app_user
            {$where}
            ORDER BY id DESC
            LIMIT {$limit}
            OFFSET {$offset}
            ",
            $params
        );

        $totalPages = max(1, (int) ceil($totalUsers / $limit));

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalUsers' => $totalUsers,
        ]);
    }

    #[Route('/users/{id}/toggle-subscription', name: 'admin_user_toggle_subscription', methods: ['POST'])]
    public function toggleSubscription(Request $request, int $id): RedirectResponse
    {
        $this->denyUnlessOwnerAdmin();

        if (!$this->isCsrfTokenValid('admin_toggle_subscription_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->findUserOr404($id);

        if ($user->isActive()) {
            $user->deactivateSubscription();
            $this->logAdminAction('disable_subscription', $id, $request);
        } else {
            $user->activateSubscription(new \DateTimeImmutable('+1 year'));
            $user->setCurrentPlan(User::PLAN_YEARLY);
            $user->resetMonthlyUsage();
            $this->logAdminAction('enable_yearly_subscription', $id, $request);
        }

        $this->em->flush();

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/reset-usage', name: 'admin_user_reset_usage', methods: ['POST'])]
    public function resetUsage(Request $request, int $id): RedirectResponse
    {
        $this->denyUnlessOwnerAdmin();

        if (!$this->isCsrfTokenValid('admin_reset_usage_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->findUserOr404($id);
        $user->resetMonthlyUsage();

        $this->logAdminAction('reset_usage', $id, $request);

        $this->em->flush();

        $this->addFlash('success', 'Quotas réinitialisés.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/toggle-verified', name: 'admin_user_toggle_verified', methods: ['POST'])]
    public function toggleVerified(Request $request, int $id): RedirectResponse
    {
        $this->denyUnlessOwnerAdmin();

        if (!$this->isCsrfTokenValid('admin_toggle_verified_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->findUserOr404($id);
        $user->setIsVerified(!$user->isVerified());

        $this->logAdminAction($user->isVerified() ? 'verify_email' : 'unverify_email', $id, $request);

        $this->em->flush();

        $this->addFlash('success', 'Statut email mis à jour.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/plan/{plan}', name: 'admin_user_change_plan', methods: ['POST'])]
    public function changePlan(Request $request, int $id, string $plan): RedirectResponse
    {
        $this->denyUnlessOwnerAdmin();

        if (!in_array($plan, [User::PLAN_MONTHLY, User::PLAN_YEARLY], true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_change_plan_' . $id . '_' . $plan, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->findUserOr404($id);
        $user->setCurrentPlan($plan);

        $this->logAdminAction('change_plan_' . $plan, $id, $request);

        $this->em->flush();

        $this->addFlash('success', 'Plan utilisateur mis à jour.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, int $id): RedirectResponse
    {
        $this->denyUnlessOwnerAdmin();

        if (!$this->isCsrfTokenValid('admin_delete_user_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->findUserOr404($id);

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('danger', 'Impossible de supprimer un administrateur.');

            return $this->redirectToRoute('admin_users');
        }

        $propertyCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM property WHERE owner_id = :userId',
            [
                'userId' => $id,
            ]
        );

        if ($propertyCount > 0) {
            $this->addFlash('danger', 'Impossible de supprimer un utilisateur avec des biens.');

            return $this->redirectToRoute('admin_users');
        }

        $this->logAdminAction('delete_user', $id, $request);

        $this->em->remove($user);
        $this->em->flush();

        $this->addFlash('success', 'Utilisateur supprimé.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}', name: 'admin_user_show', methods: ['GET'])]
    public function showUser(int $id): Response
    {
        $this->denyUnlessOwnerAdmin();

        $user = $this->findUserOr404($id);

        $properties = $this->connection->fetchAllAssociative(
            '
            SELECT
                id,
                type,
                city,
                estimate
            FROM property
            WHERE owner_id = :userId
            ORDER BY id DESC
            ',
            [
                'userId' => $id,
            ]
        );

        return $this->render('admin/user_show.html.twig', [
            'userData' => $user,
            'properties' => $properties,
        ]);
    }

    private function findUserOr404(int $id): User
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user instanceof User) {
            throw $this->createNotFoundException();
        }

        return $user;
    }

    private function denyUnlessOwnerAdmin(): void
    {
        $user = $this->getUser();

        if (!$user instanceof User || $user->getEmail() !== self::OWNER_ADMIN_EMAIL) {
            throw $this->createAccessDeniedException();
        }
    }

    private function logAdminAction(string $action, ?int $targetUserId, Request $request): void
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->connection->insert('admin_audit_log', [
            'actor_email' => $user->getEmail(),
            'target_user_id' => $targetUserId,
            'action' => $action,
            'ip_address' => $request->getClientIp(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/logs', name: 'admin_logs', methods: ['GET'])]
    public function logs(): Response
    {
        $this->denyUnlessOwnerAdmin();

        $logs = $this->connection->fetchAllAssociative('
        SELECT
            id,
            actor_email,
            target_user_id,
            action,
            ip_address,
            created_at
        FROM admin_audit_log
        ORDER BY id DESC
        LIMIT 100
    ');

        return $this->render('admin/logs.html.twig', [
            'logs' => $logs,
        ]);
    }
}
