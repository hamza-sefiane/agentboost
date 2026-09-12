<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\LocalizedDateFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AccountController extends AbstractController
{
    #[Route('/account/delete', name: 'account_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        MailerInterface $mailer,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        LocalizedDateFormatter $dateFormatter,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($user->isActive()) {
            if ($user->getStripeSubscriptionId()) {
                try {
                    $stripe = new StripeClient($stripeSecretKey);

                    $stripe->subscriptions->update(
                        $user->getStripeSubscriptionId(),
                        ['cancel_at_period_end' => true],
                        ['idempotency_key' => 'delete-account-' . $user->getId()]
                    );
                } catch (ApiErrorException) {
                    $this->addFlash('error', 'Impossible de programmer la suppression du compte. Réessayez.');

                    return $this->redirectToRoute('subscription_manage');
                }
            }

            $deleteAt = $user->getNextBillingDate();
            $user->markDeletionAtPeriodEnd($deleteAt);
            $em->flush();

            $this->sendAccountDeletionScheduledEmail($mailer, $translator, $logger, $dateFormatter, $user, $deleteAt);

            $this->addFlash(
                'success',
                'Votre compte sera supprimé automatiquement à la fin de votre période d’abonnement.'
            );

            return $this->redirectToRoute('subscription_manage');
        }

        $email = (string) $user->getEmail();
        $locale = $user->getLocale();

        $em->remove($user);
        $em->flush();

        $this->sendAccountDeletedEmail($mailer, $translator, $logger, $email, $locale);

        $security->logout(false);

        return $this->redirectToRoute('goodbye');
    }

    private function sendAccountDeletionScheduledEmail(
        MailerInterface $mailer,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        LocalizedDateFormatter $dateFormatter,
        User $user,
        ?\DateTimeInterface $deleteAt,
    ): void {
        $locale = $user->getLocale();
        try {
            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('contact@agentboost-immo.fr', 'AgentBoost'))
                    ->to((string) $user->getEmail())
                    ->subject($translator->trans('email.account_deletion.scheduled.subject', [], 'email', $locale))
                    ->locale($locale)
                    ->htmlTemplate('emails/account_deletion_scheduled.html.twig')
                    ->context([
                        'user' => $user,
                        'locale' => $locale,
                        'deleteAt' => $deleteAt,
                        'formattedDeleteAt' => $deleteAt !== null
                            ? $dateFormatter->formatLong($deleteAt, $locale)
                            : null,
                        'manageSubscriptionUrl' => $this->generateUrl(
                            'subscription_manage',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                    ])
            );
        } catch (\Throwable $exception) {
            $logger->error('Customer lifecycle email failed.', [
                'flow' => 'account_deletion_scheduled',
                'user_id' => $user->getId(),
                'exception' => $exception,
            ]);
        }
    }

    private function sendAccountDeletedEmail(
        MailerInterface $mailer,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        string $email,
        string $locale,
    ): void {
        try {
            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('contact@agentboost-immo.fr', 'AgentBoost'))
                    ->to($email)
                    ->subject($translator->trans('email.account_deletion.deleted.subject', [], 'email', $locale))
                    ->locale($locale)
                    ->htmlTemplate('emails/account_deleted.html.twig')
                    ->context(['locale' => $locale])
            );
        } catch (\Throwable $exception) {
            $logger->error('Customer lifecycle email failed.', [
                'flow' => 'account_deleted',
                'recipient_hash' => hash('sha256', $email),
                'exception' => $exception,
            ]);
        }
    }
}
