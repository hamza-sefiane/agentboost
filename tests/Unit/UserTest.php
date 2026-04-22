<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserIsInactiveByDefault(): void
    {
        $user = new User();

        $this->assertFalse($user->isActive());
        $this->assertSame('inactive', $user->getSubscriptionStatus());
        $this->assertNull($user->getNextBillingDate());
        $this->assertFalse($user->isCancelAtPeriodEnd());
        $this->assertSame('monthly', $user->getCurrentPlan());
    }

    public function testActivateSubscriptionMakesUserActive(): void
    {
        $user = new User();
        $periodEnd = new \DateTimeImmutable('+1 month');

        $user->activateSubscription($periodEnd);

        $this->assertTrue($user->isActive());
        $this->assertSame('active', $user->getSubscriptionStatus());
        $this->assertEquals($periodEnd, $user->getNextBillingDate());
        $this->assertFalse($user->isCancelAtPeriodEnd());
    }

    public function testActivateSubscriptionAppliesPendingPlan(): void
    {
        $user = new User();
        $user->setCurrentPlan('monthly');
        $user->setPendingPlan('yearly');

        $user->activateSubscription(new \DateTimeImmutable('+1 month'));

        $this->assertSame('yearly', $user->getCurrentPlan());
        $this->assertNull($user->getPendingPlan());
        $this->assertTrue($user->isActive());
    }

    public function testDeactivateSubscriptionResetsState(): void
    {
        $user = new User();
        $user->setStripeSubscriptionId('sub_123');
        $user->setPendingPlan('yearly');
        $user->activateSubscription(new \DateTimeImmutable('+1 month'));

        $user->deactivateSubscription();

        $this->assertFalse($user->isActive());
        $this->assertSame('inactive', $user->getSubscriptionStatus());
        $this->assertNull($user->getNextBillingDate());
        $this->assertNull($user->getStripeSubscriptionId());
        $this->assertFalse($user->isCancelAtPeriodEnd());
        $this->assertNull($user->getPendingPlan());
    }

    public function testMarkCancellationAtPeriodEndSetsGraceStatus(): void
    {
        $user = new User();
        $periodEnd = new \DateTimeImmutable('+1 month');

        $user->activateSubscription($periodEnd);
        $user->markCancellationAtPeriodEnd($periodEnd);

        $this->assertTrue($user->isActive());
        $this->assertSame('grace', $user->getSubscriptionStatus());
        $this->assertTrue($user->isCancelAtPeriodEnd());
        $this->assertEquals($periodEnd, $user->getNextBillingDate());
    }

    public function testUserIsInactiveIfBillingDateIsPast(): void
    {
        $user = new User();
        $user->activateSubscription(new \DateTimeImmutable('-1 day'));

        $this->assertFalse($user->isActive());
        $this->assertSame('active', $user->getSubscriptionStatus());
    }

    public function testSetCurrentPlanIgnoresInvalidValue(): void
    {
        $user = new User();
        $user->setCurrentPlan('monthly');
        $user->setCurrentPlan('invalid-plan');

        $this->assertSame('monthly', $user->getCurrentPlan());
    }

    public function testSetPendingPlanIgnoresInvalidValue(): void
    {
        $user = new User();
        $user->setPendingPlan('invalid-plan');

        $this->assertNull($user->getPendingPlan());
    }
}