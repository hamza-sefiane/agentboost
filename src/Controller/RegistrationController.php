<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Customer;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        EmailVerifier $emailVerifier
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('pricing');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                $this->addFlash('error', 'Email invalide ou mot de passe trop court (8 caractères minimum).');

                return $this->redirectToRoute('app_register');
            }

            if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Un compte existe déjà avec cet email.');

                return $this->redirectToRoute('app_register');
            }

            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            $customer = Customer::create([
                'email' => $email,
            ]);

            $user = new User();
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setStripeCustomerId($customer->id);
            $user->setIsVerified(false);

            $em->persist($user);
            $em->flush();

            $emailVerifier->sendEmailConfirmation('app_verify_email', $user);

            $security->login($user);

            $this->addFlash('success', 'Compte créé. Vérifiez votre email avant de choisir un abonnement.');

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('registration/register.html.twig');
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            return $this->redirectToRoute('pricing');
        }

        return $this->render('registration/check_email.html.twig');
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        Request $request,
        EmailVerifier $emailVerifier
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());

            return $this->redirectToRoute('app_check_email');
        }

        $this->addFlash('success', 'Email vérifié. Vous pouvez maintenant choisir un abonnement.');

        return $this->redirectToRoute('pricing');
    }
}