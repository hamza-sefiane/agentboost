<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Customer;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security
    ): Response {
        // Déjà connecté → pricing
        if ($this->getUser()) {
            return $this->redirectToRoute('pricing');
        }

        if ($request->isMethod('POST')) {
            $email    = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');

            // Validation minimale
            if (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($password) < 8
            ) {
                $this->addFlash(
                    'error',
                    'Email invalide ou mot de passe trop court (8 caractères minimum).'
                );

                return $this->redirectToRoute('app_register');
            }

            // Email unique
            if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $this->addFlash(
                    'error',
                    'Un compte existe déjà avec cet email.'
                );

                return $this->redirectToRoute('app_register');
            }

            // 🔐 Initialisation Stripe
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            // 👤 Création du customer Stripe AVANT paiement
            $customer = Customer::create([
                'email' => $email,
            ]);

            // 👤 Création utilisateur local (INACTIF)
            $user = new User();
            $user->setEmail($email);
            $user->setPassword(
                $passwordHasher->hashPassword($user, $password)
            );
            $user->setStripeCustomerId($customer->id);
            // isActive reste FALSE / NULL → sera activé par le webhook

            $em->persist($user);
            $em->flush();

            // 🔓 Login automatique
            $security->login($user);

            // 👉 Choix du plan
            return $this->redirectToRoute('pricing');
        }

        return $this->render('registration/register.html.twig');
    }
}
