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
    // ======================================================
    // CORE
    // ======================================================

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

    // ======================================================
    // ABONNEMENT — SOURCE MÉTIER
    // ======================================================

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = false;

    #[ORM\Column(name: 'next_billing_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextBillingDate = null;

    #[ORM\Column(name: 'subscription_status', length: 20)]
    private string $subscriptionStatus = 'inactive'; // inactive | active | grace

    #[ORM\Column(name: 'cancel_at_period_end', type: 'boolean')]
    private bool $cancelAtPeriodEnd = false;

    // ======================================================
    // PLAN (UI + MÉTIER)
    // ======================================================

    #[ORM\Column(name: 'current_plan', length: 10)]
    private string $currentPlan = 'monthly'; // monthly | yearly

    #[ORM\Column(name: 'pending_plan', length: 10, nullable: true)]
    private ?string $pendingPlan = null; // monthly | yearly | null

    // ======================================================
    // STRIPE
    // ======================================================

    #[ORM\Column(name: 'stripe_customer_id', length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(name: 'stripe_subscription_id', length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    // ======================================================
    // IDENTITÉ / SÉCURITÉ
    // ======================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    // ======================================================
    // LOGIQUE MÉTIER — ABONNEMENT
    // ======================================================

    /**
     * 🔑 SOURCE UNIQUE DE VÉRITÉ POUR L’ACCÈS
     * 👉 Jamais Stripe ici, jamais d’exception
     */
    public function isActive(): bool
    {
        return
            $this->active === true
            && $this->nextBillingDate !== null
            && $this->nextBillingDate > new \DateTimeImmutable();
    }

    /**
     * Stripe → invoice.payment_succeeded
     * Activation / renouvellement
     */
    public function activateSubscription(\DateTimeImmutable $periodEnd): void
    {
        $this->active = true;
        $this->subscriptionStatus = 'active';
        $this->nextBillingDate = $periodEnd;
        $this->cancelAtPeriodEnd = false;

        // 🔁 Si un downgrade était en attente, on l’applique maintenant
        if ($this->pendingPlan !== null) {
            $this->currentPlan = $this->pendingPlan;
            $this->pendingPlan = null;
        }
    }

    /**
     * Stripe → customer.subscription.deleted
     */
    public function deactivateSubscription(): void
    {
        $this->active = false;
        $this->subscriptionStatus = 'inactive';
        $this->nextBillingDate = null;
        $this->stripeSubscriptionId = null;
        $this->cancelAtPeriodEnd = false;
        $this->pendingPlan = null;
    }

    /**
     * Stripe → customer.subscription.updated
     * Résiliation programmée
     */
    public function markCancellationAtPeriodEnd(\DateTimeImmutable $periodEnd): void
    {
        $this->cancelAtPeriodEnd = true;
        $this->subscriptionStatus = 'grace';
        $this->nextBillingDate = $periodEnd;
    }

    // ======================================================
    // PLAN
    // ======================================================

    public function getCurrentPlan(): string
    {
        return $this->currentPlan;
    }

    public function setCurrentPlan(string $plan): self
    {
        if (!in_array($plan, ['monthly', 'yearly'], true)) {
            return $this;
        }

        $this->currentPlan = $plan;
        return $this;
    }

    public function getPendingPlan(): ?string
    {
        return $this->pendingPlan;
    }

    public function setPendingPlan(?string $plan): self
    {
        if ($plan !== null && !in_array($plan, ['monthly', 'yearly'], true)) {
            $plan = null;
        }

        $this->pendingPlan = $plan;
        return $this;
    }

    // ======================================================
    // STRIPE GETTERS / SETTERS
    // ======================================================

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $id): self
    {
        $this->stripeCustomerId = $id;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $id): self
    {
        $this->stripeSubscriptionId = $id;
        return $this;
    }

    public function getSubscriptionStatus(): string
    {
        return $this->subscriptionStatus;
    }

    public function getNextBillingDate(): ?\DateTimeImmutable
    {
        return $this->nextBillingDate;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }
}
