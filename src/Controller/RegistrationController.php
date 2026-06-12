<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Customer;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        EmailVerifier $emailVerifier,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactory $registrationLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('pricing');
        }

        if ($request->isMethod('POST')) {
            $limit = $registrationLimiter
                ->create($request->getClientIp() ?? 'unknown')
                ->consume();

            if (!$limit->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives. Réessayez plus tard.');
                return $this->redirectToRoute('app_register');
            }

            $email = strtolower(trim((string) $request->request->get('email')));
            $password = trim((string) $request->request->get('password'));
            $confirmPassword = trim((string) $request->request->get('confirm_password'));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Adresse email invalide.');

                return $this->redirectToRoute('app_register');
            }

            if (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->redirectToRoute('app_register');
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');

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
            $user->setPassword(
                $passwordHasher->hashPassword($user, $password)
            );
            $user->setStripeCustomerId($customer->id);
            $user->setIsVerified(false);

            $em->persist($user);
            $em->flush();

            $emailVerifier->sendEmailConfirmation('app_verify_email', $user);

            $this->addFlash('success', 'Compte créé. Vérifiez votre email.');

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('registration/register.html.twig');
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('registration/check_email.html.twig');
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        EmailVerifier $emailVerifier
    ): Response {
        $id = $request->query->get('id');

        if (!$id) {
            $this->addFlash('error', 'Lien de vérification invalide.');

            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->find($id);

        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur introuvable.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());

            return $this->redirectToRoute('app_check_email');
        }

        $this->addFlash(
            'success',
            'Email vérifié. Connectez-vous pour choisir un abonnement.'
        );

        return $this->redirectToRoute('app_login');
    }
}
