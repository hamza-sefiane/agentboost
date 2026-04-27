<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = false;

    #[ORM\Column(name: 'next_billing_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextBillingDate = null;

    #[ORM\Column(name: 'subscription_status', length: 20)]
    private string $subscriptionStatus = 'inactive';

    #[ORM\Column(name: 'cancel_at_period_end', type: 'boolean')]
    private bool $cancelAtPeriodEnd = false;

    #[ORM\Column(name: 'current_plan', length: 10)]
    private string $currentPlan = 'monthly';

    #[ORM\Column(name: 'pending_plan', length: 10, nullable: true)]
    private ?string $pendingPlan = null;

    #[ORM\Column(name: 'stripe_customer_id', length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(name: 'stripe_subscription_id', length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(name: 'company_name', length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(name: 'company_address', length: 255, nullable: true)]
    private ?string $companyAddress = null;

    #[ORM\Column(name: 'company_phone', length: 50, nullable: true)]
    private ?string $companyPhone = null;

    #[ORM\Column(name: 'company_logo', length: 255, nullable: true)]
    private ?string $companyLogo = null;

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        return array_values(array_unique($this->roles));
    }

    public function setRoles(array $roles): self
    {
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string { return $this->password; }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function isActive(): bool
    {
        return $this->active === true
            && $this->nextBillingDate !== null
            && $this->nextBillingDate > new \DateTimeImmutable();
    }

    public function activateSubscription(\DateTimeImmutable $periodEnd): void
    {
        $this->active = true;
        $this->subscriptionStatus = 'active';
        $this->nextBillingDate = $periodEnd;
        $this->cancelAtPeriodEnd = false;

        if ($this->pendingPlan !== null) {
            $this->currentPlan = $this->pendingPlan;
            $this->pendingPlan = null;
        }
    }

    public function deactivateSubscription(): void
    {
        $this->active = false;
        $this->subscriptionStatus = 'inactive';
        $this->nextBillingDate = null;
        $this->stripeSubscriptionId = null;
        $this->cancelAtPeriodEnd = false;
        $this->pendingPlan = null;
    }

    public function markCancellationAtPeriodEnd(\DateTimeImmutable $periodEnd): void
    {
        $this->cancelAtPeriodEnd = true;
        $this->subscriptionStatus = 'grace';
        $this->nextBillingDate = $periodEnd;
    }

    public function getCurrentPlan(): string { return $this->currentPlan; }

    public function setCurrentPlan(string $plan): self
    {
        if (in_array($plan, ['monthly', 'yearly'], true)) {
            $this->currentPlan = $plan;
        }

        return $this;
    }

    public function getPendingPlan(): ?string { return $this->pendingPlan; }

    public function setPendingPlan(?string $plan): self
    {
        $this->pendingPlan = $plan !== null && in_array($plan, ['monthly', 'yearly'], true)
            ? $plan
            : null;

        return $this;
    }

    public function getStripeCustomerId(): ?string { return $this->stripeCustomerId; }

    public function setStripeCustomerId(?string $id): self
    {
        $this->stripeCustomerId = $id;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string { return $this->stripeSubscriptionId; }

    public function setStripeSubscriptionId(?string $id): self
    {
        $this->stripeSubscriptionId = $id;
        return $this;
    }

    public function getSubscriptionStatus(): string { return $this->subscriptionStatus; }

    public function getNextBillingDate(): ?\DateTimeImmutable { return $this->nextBillingDate; }

    public function isCancelAtPeriodEnd(): bool { return $this->cancelAtPeriodEnd; }

    public function getCompanyName(): ?string { return $this->companyName; }

    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getCompanyAddress(): ?string { return $this->companyAddress; }

    public function setCompanyAddress(?string $companyAddress): self
    {
        $this->companyAddress = $companyAddress;
        return $this;
    }

    public function getCompanyPhone(): ?string { return $this->companyPhone; }

    public function setCompanyPhone(?string $companyPhone): self
    {
        $this->companyPhone = $companyPhone;
        return $this;
    }

    public function getCompanyLogo(): ?string { return $this->companyLogo; }

    public function setCompanyLogo(?string $companyLogo): self
    {
        $this->companyLogo = $companyLogo;
        return $this;
    }
}