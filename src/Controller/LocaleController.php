<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'change_locale')]
    public function changeLocale(
        string $locale,
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {

        $allowedLocales = ['fr', 'en', 'es'];

        if (!in_array($locale, $allowedLocales, true)) {
            $locale = 'fr';
        }

        $session->set('_locale', $locale);

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($locale);
            $entityManager->flush();
        }

        $referer = $request->headers->get('referer');

        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
    }
}
