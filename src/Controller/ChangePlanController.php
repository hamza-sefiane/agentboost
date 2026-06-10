<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ChangePlanController extends AbstractController
{
    #[Route(
        '/subscription/change-plan/{plan}',
        name: 'subscription_change_plan',
        methods: ['POST'],
        requirements: ['plan' => 'monthly|yearly']
    )]
    public function __invoke(
        string $plan,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire('%env(STRIPE_PRICE_MONTHLY)%')] string $priceMonthly,
        #[Autowire('%env(STRIPE_PRICE_YEARLY)%')] string $priceYearly,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('subscription_change_plan_' . $plan, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        if (!$user instanceof User || !$user->getStripeSubscriptionId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$user->isActive() || $user->isCancelAtPeriodEnd()) {
            throw $this->createAccessDeniedException();
        }

        // Rien à faire
        if ($plan === $user->getCurrentPlan()) {
            return $this->redirectToRoute('dashboard');
        }

        $priceId = match ($plan) {
            'monthly' => $priceMonthly,
            'yearly' => $priceYearly,
        };

        Stripe::setApiKey($stripeSecretKey);

        try {
            $subscription = Subscription::retrieve(
                $user->getStripeSubscriptionId()
            );

            $isDowngrade =
                $user->getCurrentPlan() === 'yearly'
                && $plan === 'monthly';

            Subscription::update(
                $subscription->id,
                [
                    'items' => [[
                        'id' => $subscription->items->data[0]->id,
                        'price' => $priceId,
                    ]],
                    'proration_behavior' => 'create_prorations',
                ]
            );

            // Upgrade immédiat
            if ($plan === 'yearly') {
                $user->setCurrentPlan('yearly');
                $user->setPendingPlan(null);
            }

            // Downgrade différé
            if ($isDowngrade) {
                $user->setPendingPlan('monthly');
            }

            $em->flush();

            $this->addFlash(
                'success',
                $plan === 'yearly'
                    ? 'Abonnement annuel activé.'
                    : 'Retour au mensuel programmé.'
            );

        } catch (ApiErrorException) {
            $this->addFlash(
                'error',
                'Impossible de changer le plan.'
            );
        }

        return $this->redirectToRoute('dashboard');
    }
}
