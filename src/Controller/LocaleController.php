<?php

namespace App\Controller;

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
        SessionInterface $session
    ): RedirectResponse {

        $allowedLocales = ['fr', 'en', 'es'];

        if (!in_array($locale, $allowedLocales, true)) {
            $locale = 'fr';
        }

        $session->set('_locale', $locale);

        $referer = $request->headers->get('referer');

        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
    }
}
