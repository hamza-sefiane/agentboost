<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class SubscriptionMailer implements SubscriptionMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private LocalizedDateFormatter $dateFormatter,
    ) {}

    public function sendWelcomeEmail(string $to, string $prenom): void
    {
        $html = $this->twig->render('emails/welcome.html.twig', ['prenom' => $prenom]);

        $email = (new Email())
            ->from('support@agentboost.app')
            ->to($to)
            ->subject('Bienvenue sur AgentBoost')
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendActivationEmail(User $user, \DateTimeInterface $endDate): void
    {
        $locale = $user->getLocale();
        $email = (new TemplatedEmail())
            ->from('support@agentboost.app')
            ->to((string) $user->getEmail())
            ->subject($this->translator->trans('email.subscription.activation.subject', [], 'email', $locale))
            ->locale($locale)
            ->htmlTemplate('emails/subscription_activated.html.twig')
            ->context([
                'locale' => $locale,
                'formattedEndDate' => $this->dateFormatter->formatLong($endDate, $locale),
                'accountUrl' => $this->urlGenerator->generate(
                    'app_login',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ]);

        $this->mailer->send($email);
    }

    public function sendCancellationEmail(User $user, \DateTimeInterface $endDate): void
    {
        $locale = $user->getLocale();
        $email = (new TemplatedEmail())
            ->from('support@agentboost.app')
            ->to((string) $user->getEmail())
            ->subject($this->translator->trans('email.subscription.cancellation.subject', [], 'email', $locale))
            ->locale($locale)
            ->htmlTemplate('emails/subscription_cancelled.html.twig')
            ->context([
                'locale' => $locale,
                'formattedEndDate' => $this->dateFormatter->formatLong($endDate, $locale),
                'manageSubscriptionUrl' => $this->urlGenerator->generate(
                    'subscription_manage',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ]);

        $this->mailer->send($email);
    }
}
