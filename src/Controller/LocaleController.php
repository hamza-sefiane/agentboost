<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'change_locale', requirements: ['locale' => 'fr|en|es'])]
    public function __invoke(string $locale, Request $request): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('home'));
    }
}
