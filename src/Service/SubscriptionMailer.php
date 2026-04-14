<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class SubscriptionMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig
    ) {}

    /**
     * 📧 Email de bienvenue à l’inscription
     */
    public function sendWelcomeEmail(
        string $to,
        string $prenom
    ): void {
        $html = $this->twig->render(
            'emails/welcome.html.twig',
            [
                'prenom' => $prenom,
            ]
        );

        $email = (new Email())
            ->from('support@agentboost.app')
            ->to($to)
            ->subject('Bienvenue sur AgentBoost')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * 📧 Email d’activation de l’abonnement
     * ➜ Envoyé UNE SEULE FOIS lors de la première activation
     */
    public function sendActivationEmail(
        string $to,
        string $prenom,
        string $plan
    ): void {
        $html = $this->twig->render(
            'emails/subscription_activated.html.twig',
            [
                'prenom' => $prenom,
                'plan' => $plan,
            ]
        );

        $email = (new Email())
            ->from('support@agentboost.app')
            ->to($to)
            ->subject('Votre abonnement AgentBoost est actif')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * 📧 Email de résiliation programmée
     * ➜ Envoyé UNE SEULE FOIS quand cancel_at_period_end = true
     */
    public function sendCancellationEmail(
        string $to,
        string $prenom,
        \DateTimeInterface $endDate
    ): void {
        file_put_contents(
            dirname(__DIR__, 2) . '/var/log/mail.log',
            sprintf(
                "[%s] sendCancellationEmail → %s | end=%s\n",
                date('Y-m-d H:i:s'),
                $to,
                $endDate->format('Y-m-d H:i:s')
            ),
            FILE_APPEND
        );

        $html = $this->twig->render(
            'emails/subscription_cancelled.html.twig',
            [
                'prenom' => $prenom,
                'subscription_end_date' => $endDate,
            ]
        );

        $email = (new Email())
            ->from('support@agentboost.app')
            ->to($to)
            ->subject('Confirmation de résiliation de votre abonnement')
            ->html($html);

        $this->mailer->send($email);
    }
}