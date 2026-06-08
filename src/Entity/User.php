<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const PLAN_MONTHLY = 'monthly';
    public const PLAN_YEARLY = 'yearly';

    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_GRACE = 'grace';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(name: 'is_verified', type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'next_billing_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextBillingDate = null;

    #[ORM\Column(name: 'subscription_status', length: 20)]
    private string $subscriptionStatus = self::STATUS_INACTIVE;

    #[ORM\Column(name: 'cancel_at_period_end', type: 'boolean')]
    private bool $cancelAtPeriodEnd = false;

    #[ORM\Column(name: 'delete_at_period_end', type: 'boolean')]
    private bool $deleteAtPeriodEnd = false;

    #[ORM\Column(name: 'current_plan', length: 10)]
    private string $currentPlan = self::PLAN_MONTHLY;

    #[ORM\Column(name: 'pending_plan', length: 10, nullable: true)]
    private ?string $pendingPlan = null;

    #[ORM\Column(name: 'monthly_estimations', type: 'integer', options: ['default' => 0])]
    private int $monthlyEstimations = 0;

    #[ORM\Column(name: 'monthly_ai_generations', type: 'integer', options: ['default' => 0])]
    private int $monthlyAiGenerations = 0;

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

    #[ORM\Column(name: 'agency_street', length: 255, nullable: true)]
    private ?string $agencyStreet = null;

    #[ORM\Column(name: 'agency_address_complement', length: 255, nullable: true)]
    private ?string $agencyAddressComplement = null;

    #[ORM\Column(name: 'agency_postal_code', length: 20, nullable: true)]
    private ?string $agencyPostalCode = null;

    #[ORM\Column(name: 'agency_city', length: 120, nullable: true)]
    private ?string $agencyCity = null;

    #[ORM\Column(name: 'agency_email', length: 180, nullable: true)]
    private ?string $agencyEmail = null;

    #[ORM\Column(name: 'agency_website', length: 255, nullable: true)]
    private ?string $agencyWebsite = null;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    /**
     * @var Collection<int, AdminAuditLog>
     */
    #[ORM\OneToMany(targetEntity: AdminAuditLog::class, mappedBy: 'targetUser')]
    private Collection $adminAuditLogs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->notifications = new ArrayCollection();
        $this->adminAuditLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $roles[] = 'ROLE_USER';
        $this->roles = array_values(array_unique($roles));

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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->nextBillingDate !== null
            && $this->nextBillingDate > new \DateTimeImmutable();
    }

    public function activateSubscription(\DateTimeImmutable $periodEnd): void
    {
        $this->active = true;
        $this->subscriptionStatus = self::STATUS_ACTIVE;
        $this->nextBillingDate = $periodEnd;
        $this->cancelAtPeriodEnd = false;
        $this->deleteAtPeriodEnd = false;

        if ($this->pendingPlan !== null) {
            $this->currentPlan = $this->pendingPlan;
            $this->pendingPlan = null;
        }
    }

    public function deactivateSubscription(): void
    {
        $this->active = false;
        $this->subscriptionStatus = self::STATUS_INACTIVE;
        $this->nextBillingDate = null;
        $this->cancelAtPeriodEnd = false;
        $this->deleteAtPeriodEnd = false;
        $this->pendingPlan = null;
        $this->stripeSubscriptionId = null;
    }

    public function markCancellationAtPeriodEnd(\DateTimeImmutable $periodEnd): void
    {
        $this->active = true;
        $this->cancelAtPeriodEnd = true;
        $this->subscriptionStatus = self::STATUS_GRACE;
        $this->nextBillingDate = $periodEnd;
    }

    public function cancelCancellationAtPeriodEnd(): void
    {
        $this->cancelAtPeriodEnd = false;
        $this->subscriptionStatus = self::STATUS_ACTIVE;
    }

    public function markDeletionAtPeriodEnd(?\DateTimeImmutable $periodEnd = null): void
    {
        $this->deleteAtPeriodEnd = true;
        $this->cancelAtPeriodEnd = true;
        $this->subscriptionStatus = self::STATUS_GRACE;

        if ($periodEnd !== null) {
            $this->nextBillingDate = $periodEnd;
        }
    }

    public function cancelDeletionAtPeriodEnd(): void
    {
        $this->deleteAtPeriodEnd = false;
    }

    public function isDeleteAtPeriodEnd(): bool
    {
        return $this->deleteAtPeriodEnd;
    }

    public function setDeleteAtPeriodEnd(bool $deleteAtPeriodEnd): self
    {
        $this->deleteAtPeriodEnd = $deleteAtPeriodEnd;

        return $this;
    }

    public function getCurrentPlan(): string
    {
        return $this->currentPlan;
    }

    public function setCurrentPlan(string $plan): self
    {
        if (in_array($plan, [self::PLAN_MONTHLY, self::PLAN_YEARLY], true)) {
            $this->currentPlan = $plan;
        }

        return $this;
    }

    public function getPendingPlan(): ?string
    {
        return $this->pendingPlan;
    }

    public function setPendingPlan(?string $plan): self
    {
        $this->pendingPlan = in_array($plan, [self::PLAN_MONTHLY, self::PLAN_YEARLY], true)
            ? $plan
            : null;

        return $this;
    }

    public function getMonthlyEstimations(): int
    {
        return $this->monthlyEstimations;
    }

    public function setMonthlyEstimations(int $monthlyEstimations): self
    {
        $this->monthlyEstimations = max(0, $monthlyEstimations);

        return $this;
    }

    public function incrementMonthlyEstimations(): self
    {
        ++$this->monthlyEstimations;

        return $this;
    }

    public function resetMonthlyEstimations(): self
    {
        $this->monthlyEstimations = 0;

        return $this;
    }

    public function getMonthlyAiGenerations(): int
    {
        return $this->monthlyAiGenerations;
    }

    public function setMonthlyAiGenerations(int $monthlyAiGenerations): self
    {
        $this->monthlyAiGenerations = max(0, $monthlyAiGenerations);

        return $this;
    }

    public function incrementMonthlyAiGenerations(): self
    {
        ++$this->monthlyAiGenerations;

        return $this;
    }

    public function resetMonthlyAiGenerations(): self
    {
        $this->monthlyAiGenerations = 0;

        return $this;
    }

    public function resetMonthlyUsage(): self
    {
        $this->monthlyEstimations = 0;
        $this->monthlyAiGenerations = 0;

        return $this;
    }

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

    public function setSubscriptionStatus(string $subscriptionStatus): self
    {
        if (in_array($subscriptionStatus, [
            self::STATUS_INACTIVE,
            self::STATUS_ACTIVE,
            self::STATUS_GRACE,
        ], true)) {
            $this->subscriptionStatus = $subscriptionStatus;
        }

        return $this;
    }

    public function getNextBillingDate(): ?\DateTimeImmutable
    {
        return $this->nextBillingDate;
    }

    public function setNextBillingDate(?\DateTimeImmutable $nextBillingDate): self
    {
        $this->nextBillingDate = $nextBillingDate;

        return $this;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function setCancelAtPeriodEnd(bool $cancelAtPeriodEnd): self
    {
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = $this->cleanNullableString($companyName);

        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(?string $companyAddress): self
    {
        $this->companyAddress = $this->cleanNullableString($companyAddress);

        return $this;
    }

    public function getCompanyPhone(): ?string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(?string $companyPhone): self
    {
        $this->companyPhone = $this->cleanNullableString($companyPhone);

        return $this;
    }

    public function getCompanyLogo(): ?string
    {
        return $this->companyLogo;
    }

    public function setCompanyLogo(?string $companyLogo): self
    {
        $this->companyLogo = $this->cleanNullableString($companyLogo);

        return $this;
    }

    public function getAgencyStreet(): ?string
    {
        return $this->agencyStreet;
    }

    public function setAgencyStreet(?string $agencyStreet): self
    {
        $this->agencyStreet = $this->cleanNullableString($agencyStreet);

        return $this;
    }

    public function getAgencyAddressComplement(): ?string
    {
        return $this->agencyAddressComplement;
    }

    public function setAgencyAddressComplement(?string $agencyAddressComplement): self
    {
        $this->agencyAddressComplement = $this->cleanNullableString($agencyAddressComplement);

        return $this;
    }

    public function getAgencyPostalCode(): ?string
    {
        return $this->agencyPostalCode;
    }

    public function setAgencyPostalCode(?string $agencyPostalCode): self
    {
        $postalCode = $this->cleanNullableString($agencyPostalCode);

        $this->agencyPostalCode = $postalCode !== null
            ? preg_replace('/\D/', '', $postalCode)
            : null;

        return $this;
    }

    public function getAgencyCity(): ?string
    {
        return $this->agencyCity;
    }

    public function setAgencyCity(?string $agencyCity): self
    {
        $this->agencyCity = $this->cleanNullableString($agencyCity);

        return $this;
    }

    public function getAgencyEmail(): ?string
    {
        return $this->agencyEmail;
    }

    public function setAgencyEmail(?string $agencyEmail): self
    {
        $email = $this->cleanNullableString($agencyEmail);

        $this->agencyEmail = $email !== null ? mb_strtolower($email) : null;

        return $this;
    }

    public function getAgencyWebsite(): ?string
    {
        return $this->agencyWebsite;
    }

    public function setAgencyWebsite(?string $agencyWebsite): self
    {
        $this->agencyWebsite = $this->cleanNullableString($agencyWebsite);

        return $this;
    }

    public function getAgencyFullAddress(): ?string
    {
        $parts = array_filter([
            $this->agencyStreet,
            $this->agencyAddressComplement,
            trim(sprintf(
                '%s %s',
                $this->agencyPostalCode ?? '',
                $this->agencyCity ?? ''
            )),
        ]);

        return $parts !== [] ? implode("\n", $parts) : $this->companyAddress;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    private function cleanNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return Collection<int, AdminAuditLog>
     */
    public function getAdminAuditLogs(): Collection
    {
        return $this->adminAuditLogs;
    }

    public function addAdminAuditLog(AdminAuditLog $adminAuditLog): static
    {
        if (!$this->adminAuditLogs->contains($adminAuditLog)) {
            $this->adminAuditLogs->add($adminAuditLog);
            $adminAuditLog->setTargetUser($this);
        }

        return $this;
    }

    public function removeAdminAuditLog(AdminAuditLog $adminAuditLog): static
    {
        if ($this->adminAuditLogs->removeElement($adminAuditLog)) {
            // set the owning side to null (unless already changed)
            if ($adminAuditLog->getTargetUser() === $this) {
                $adminAuditLog->setTargetUser(null);
            }
        }

        return $this;
    }
}
