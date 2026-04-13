<?php

namespace App\Controller;

use App\Entity\User;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BillingPortalController extends AbstractController
{
    #[Route('/billing/portal', name: 'billing_portal', methods: ['GET'])]
    public function __invoke(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(STRIPE_SECRET_KEY)%')]
        string $stripeSecretKey
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        // Sécurité absolue
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$user->getStripeCustomerId()) {
            throw new \LogicException('Stripe customer missing for user '.$user->getId());
        }

        Stripe::setApiKey($stripeSecretKey);

        $session = BillingPortalSession::create([
            'customer' => $user->getStripeCustomerId(),

            // ⚠️ TOUJOURS une page publique
            'return_url' => $this->generateUrl(
                'goodbye',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);

        return new RedirectResponse($session->url);
    }
}
