<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationInternationalizationTest extends WebTestCase
{
    #[DataProvider('localeProvider')]
    public function testRegistrationFormUsesAnonymousVisitorLocale(
        string $locale,
        string $title,
        string $emailLabel,
        string $submitLabel,
    ): void {
        $client = static::createClient();
        $client->request('GET', '/change-locale/'.$locale);

        $crawler = $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSame($locale, $crawler->filter('html')->attr('lang'));
        self::assertSame($title, trim($crawler->filter('.auth-title')->text()));
        self::assertSame($emailLabel, trim($crawler->filter('label[for="email"]')->text()));
        self::assertSame($submitLabel, trim($crawler->filter('#submitButton')->text()));
        self::assertNotSame(
            'register.validation.invalid_email',
            $crawler->filter('#registerForm')->attr('data-email-error'),
        );
    }

    public static function localeProvider(): iterable
    {
        yield 'French' => ['fr', 'Créer un compte', 'Adresse email', 'Créer mon compte'];
        yield 'English' => ['en', 'Create an account', 'Email address', 'Create my account'];
        yield 'Spanish' => ['es', 'Crear una cuenta', 'Correo electrónico', 'Crear mi cuenta'];
    }

    public function testChangingLanguageImmediatelyTranslatesRegistrationForm(): void
    {
        $client = static::createClient();

        $client->request('GET', '/change-locale/en', server: ['HTTP_REFERER' => '/register']);
        self::assertResponseRedirects('/register');

        $crawler = $client->followRedirect();

        self::assertSame('Create an account', trim($crawler->filter('.auth-title')->text()));
        self::assertSame('Create my account', trim($crawler->filter('#submitButton')->text()));
    }
}
