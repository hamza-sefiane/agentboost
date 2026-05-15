<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityAccessTest extends WebTestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    public function testResetPasswordPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testPricingPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/pricing');

        self::assertResponseIsSuccessful();
    }
}