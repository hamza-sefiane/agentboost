<?php

namespace App\Tests\Unit;

use App\Entity\StripeEvent;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\StripeWebhookHandler;
use App\Service\SubscriptionMailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Stripe\Event;
use Stripe\Subscription;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StripeWebhookHandlerTest extends TestCase
{
    private function createEntityManagerMock(
        UserRepository $userRepository,
        ?StripeEvent $existingStripeEvent = null,
        bool $expectsFlush = true
    ): EntityManagerInterface {
        $stripeEventRepository = $this->createMock(EntityRepository::class);
        $stripeEventRepository
            ->method('findOneBy')
            ->willReturn($existingStripeEvent);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager
            ->method('getRepository')
            ->willReturnCallback(function (string $class) use ($userRepository, $stripeEventRepository) {
                return match ($class) {
                    User::class => $userRepository,
                    StripeEvent::class => $stripeEventRepository,
                    default => throw new \RuntimeException('Unexpected repository: ' . $class),
                };
            });

        $entityManager
            ->expects($expectsFlush ? $this->once() : $this->never())
            ->method('flush');

        $entityManager
            ->method('persist');

        return $entityManager;
    }

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

        $entityManager = $this->createEntityManagerMock($repository);

        $mailer = $this->createMock(SubscriptionMailerInterface::class);
        $mailer->expects($this->never())->method('sendActivationEmail');
        $mailer->expects($this->never())->method('sendCancellationEmail');
        $mailer->expects($this->never())->method('sendWelcomeEmail');

        $params = $this->createMock(ParameterBagInterface::class);

        $handler = new StripeWebhookHandler($entityManager, $mailer, $params, new NullLogger());

        $subscription = Subscription::constructFrom([
            'customer' => 'cus_123',
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_test_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => $subscription],
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

        $entityManager = $this->createEntityManagerMock($repository);

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

        $handler = new StripeWebhookHandler($entityManager, $mailer, $params, new NullLogger());

        $periodEnd = time() + 3600;

        $subscription = Subscription::constructFrom([
            'customer' => 'cus_456',
            'cancel_at' => null,
            'cancel_at_period_end' => true,
            'current_period_end' => $periodEnd,
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_test_updated_grace',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => $subscription],
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

        $entityManager = $this->createEntityManagerMock($repository, null, true);

        $mailer = $this->createMock(SubscriptionMailerInterface::class);
        $mailer->expects($this->never())->method('sendCancellationEmail');
        $mailer->expects($this->never())->method('sendActivationEmail');
        $mailer->expects($this->never())->method('sendWelcomeEmail');

        $params = $this->createMock(ParameterBagInterface::class);

        $handler = new StripeWebhookHandler($entityManager, $mailer, $params, new NullLogger());

        $subscription = Subscription::constructFrom([
            'customer' => 'cus_789',
            'cancel_at' => null,
            'cancel_at_period_end' => true,
            'current_period_end' => $periodEnd->getTimestamp(),
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_test_updated_already_cancelled',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => $subscription],
        ]);

        $handler->handle($event);

        $this->assertSame('grace', $user->getSubscriptionStatus());
        $this->assertTrue($user->isCancelAtPeriodEnd());
        $this->assertEquals($periodEnd, $user->getNextBillingDate());
    }
}