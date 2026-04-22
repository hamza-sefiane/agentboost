<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\StripeWebhookHandler;
use App\Service\SubscriptionMailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Stripe\Event;
use Stripe\Subscription;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StripeWebhookHandlerTest extends TestCase
{
    public function testHandleSubscriptionDeletedDeactivatesUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setStripeCustomerId('cus_123');
        $user->setStripeSubscriptionId('sub_123');
        $user->activateSubscription(new \DateTimeImmutable('+1 month'));

        $repository = $this->createMock(UserRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['stripeCustomerId' => 'cus_123'])
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $entityManager
            ->expects($this->once())
            ->method('flush');

        $mailer = $this->createMock(SubscriptionMailerInterface::class);
        $mailer->expects($this->never())->method('sendActivationEmail');
        $mailer->expects($this->never())->method('sendCancellationEmail');
        $mailer->expects($this->never())->method('sendWelcomeEmail');

        $params = $this->createMock(ParameterBagInterface::class);

        $handler = new StripeWebhookHandler($entityManager, $mailer, $params);

        $subscription = Subscription::constructFrom([
            'customer' => 'cus_123',
        ]);

        $event = Event::constructFrom([
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => $subscription,
            ],
        ]);

        $handler->handle($event);

        $this->assertFalse($user->isActive());
        $this->assertSame('inactive', $user->getSubscriptionStatus());
        $this->assertNull($user->getNextBillingDate());
        $this->assertNull($user->getStripeSubscriptionId());
        $this->assertFalse($user->isCancelAtPeriodEnd());
    }

    public function testHandleSubscriptionUpdatedMarksGraceAndSendsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setStripeCustomerId('cus_456');
        $user->activateSubscription(new \DateTimeImmutable('+1 month'));

        $repository = $this->createMock(UserRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['stripeCustomerId' => 'cus_456'])
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $entityManager
            ->expects($this->once())
            ->method('flush');

        $mailer = $this->createMock(SubscriptionMailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('sendCancellationEmail')
            ->with(
                'test@example.com',
                'Utilisateur',
                $this->isInstanceOf(\DateTimeInterface::class)
            );

        $mailer->expects($this->never())->method('sendActivationEmail');
        $mailer->expects($this->never())->method('sendWelcomeEmail');

        $params = $this->createMock(ParameterBagInterface::class);

        $handler = new StripeWebhookHandler($entityManager, $mailer, $params);

        $periodEnd = time() + 3600;

        $subscription = Subscription::constructFrom([
            'customer' => 'cus_456',
            'cancel_at' => null,
            'cancel_at_period_end' => true,
            'current_period_end' => $periodEnd,
        ]);

        $event = Event::constructFrom([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => $subscription,
            ],
        ]);

        $handler->handle($event);

        $this->assertTrue($user->isActive());
        $this->assertSame('grace', $user->getSubscriptionStatus());
        $this->assertTrue($user->isCancelAtPeriodEnd());
        $this->assertEquals(
            (new \DateTimeImmutable())->setTimestamp($periodEnd),
            $user->getNextBillingDate()
        );
    }

    public function testHandleSubscriptionUpdatedDoesNothingIfAlreadyMarkedForCancellation(): void
    {
        $periodEnd = new \DateTimeImmutable('+1 month');

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setStripeCustomerId('cus_789');
        $user->activateSubscription($periodEnd);
        $user->markCancellationAtPeriodEnd($periodEnd);

        $repository = $this->createMock(UserRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['stripeCustomerId' => 'cus_789'])
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $entityManager
            ->expects($this->never())
            ->method('flush');

        $mailer = $this->createMock(SubscriptionMailerInterface::class);
        $mailer->expects($this->never())->method('sendCancellationEmail');
        $mailer->expects($this->never())->method('sendActivationEmail');
        $mailer->expects($this->never())->method('sendWelcomeEmail');

        $params = $this->createMock(ParameterBagInterface::class);

        $handler = new StripeWebhookHandler($entityManager, $mailer, $params);

        $subscription = Subscription::constructFrom([
            'customer' => 'cus_789',
            'cancel_at' => null,
            'cancel_at_period_end' => true,
            'current_period_end' => $periodEnd->getTimestamp(),
        ]);

        $event = Event::constructFrom([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => $subscription,
            ],
        ]);

        $handler->handle($event);

        $this->assertSame('grace', $user->getSubscriptionStatus());
        $this->assertTrue($user->isCancelAtPeriodEnd());
        $this->assertEquals($periodEnd, $user->getNextBillingDate());
    }
}