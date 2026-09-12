<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {}

    public function sendEmailConfirmation(string $routeName, User $user, string $locale): void
    {
        $locale = $this->validateLocale($locale);
        $signature = $this->verifyEmailHelper->generateSignature(
            $routeName,
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        $email = (new TemplatedEmail())
            ->from(new Address('contact@agentboost-immo.fr', 'AgentBoost'))
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.verify.subject', [], 'email', $locale))
            ->locale($locale)
            ->htmlTemplate('registration/confirmation_email.html.twig')
            ->context([
                'signedUrl' => $signature->getSignedUrl(),
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            $user->getId(),
            $user->getEmail()
        );

        $user->setIsVerified(true);
        $this->em->flush();
    }

    private function validateLocale(string $locale): string
    {
        return in_array($locale, ['fr', 'en', 'es'], true) ? $locale : 'fr';
    }
}
