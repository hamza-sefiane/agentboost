# AgentBoost – README Technique

Ce document décrit **la réalité technique actuelle du projet AgentBoost**.
Il complète le README produit et sert de **référence développeur unique**.

👉 Ce document **décrit l’existant**, il ne spécule pas sur des refactors futurs.

---

## 🎯 Philosophie générale

AgentBoost est un **SaaS piloté par l’état d’abonnement**.

Principes non négociables :

* **Stripe est la source unique de vérité pour les paiements**
* **Aucune logique métier critique ne dépend de l’UI Stripe**
* **Toute activation / désactivation passe exclusivement par les webhooks**
* **Le backend décide, jamais Stripe UI**

---

## 🧱 Stack technique

* **Backend** : Symfony 7.x (PHP)
* **Base de données** : MySQL
* **Paiement / abonnements** : Stripe (Subscriptions + Billing Portal)
* **Templates** : Twig
* **Emails** : Symfony Mailer
* **Environnement email local** : Mailpit
* **Webhooks** : Stripe CLI / endpoint HTTP sécurisé

---

## 👤 Entité User (cœur métier)

L’entité `User` porte **toute la logique d’accès et d’abonnement**.

### Champs métier utilisés

* `is_active` (bool)
* `subscription_status` (`inactive | active | grace`)
* `next_billing_date` (`DateTimeImmutable`)
* `cancel_at_period_end` (bool)
* `stripeCustomerId`
* `stripeSubscriptionId`
* `current_plan`

---

### ⚠️ Clarification importante sur `subscription_status`

```text
grace ≠ Stripe grace period
```

Dans AgentBoost :

* `grace` signifie :

  > **abonnement actif avec annulation programmée à la fin de la période payée**
* Ce n’est **PAS** une période de retry Stripe après échec de paiement

Ce choix est **volontaire** et assumé.

---

## 🔐 Règle d’accès utilisateur

La règle d’accès est **volontairement simple et robuste**.

```php
public function isActive(): bool
```

Un utilisateur est considéré **ACTIF** si et seulement si :

* `is_active = true`
* `next_billing_date > now()`

📌 **Important** :

* `cancel_at_period_end` **n’invalide jamais l’accès**
* L’accès est maintenu **jusqu’à la fin de la période payée**

---

## 💳 Stripe – Architecture retenue

### ❌ Événements Stripe volontairement ignorés

* `checkout.session.completed`
* Pages de succès / annulation Stripe

📌 Raison :

* événements **UI-dépendants**
* non fiables métier
* non idempotents

---

### ✅ Événements Stripe utilisés

(**SOURCE UNIQUE DE VÉRITÉ**)

---

### 1️⃣ `invoice.payment_succeeded`

📍 **Événement central du système**

Utilisé pour :

* activation initiale
* renouvellement d’abonnement

Actions réalisées :

* `is_active = true`
* `subscription_status = active`
* `next_billing_date = invoice.lines.data[0].period.end`
* mise à jour du plan si nécessaire

📌 **Règle critique** :

* **NE JAMAIS** utiliser `subscription.current_period_end`
* **TOUJOURS** utiliser la période réellement facturée :

  ```php
  invoice.lines.data[0].period.end
  ```

📌 **Bug historique corrigé** :

* timestamp `1970` causé par une mauvaise source de date

---

### 2️⃣ `customer.subscription.updated`

Utilisé **exclusivement** pour détecter une **annulation programmée**
(via Billing Portal ou API Stripe).

Critères Stripe acceptés :

* `cancel_at !== null`
* **OU**
* `cancel_at_period_end === true`

Actions :

* passage `active → grace`
* `cancel_at_period_end = true`
* envoi d’un **email d’annulation UNE SEULE FOIS**

📌 **Garde-fou métier** :

* si `cancel_at_period_end = true`
  → aucun nouvel email n’est envoyé

---

### 3️⃣ `customer.subscription.deleted`

📍 **Fin effective de l’abonnement**

Actions :

* `is_active = false`
* accès coupé
* nettoyage éventuel des états

📌 Aucune désactivation anticipée n’est autorisée avant cet événement.

---

## 📧 Emails

* Envoi via Symfony Mailer
* Templates Twig dédiés
* Emails envoyés **uniquement par le backend**
* Aucun email déclenché par l’UI Stripe

### Emails existants

* Confirmation d’annulation (1 seule fois)
* Notifications liées à l’abonnement

---

## 🧭 Stripe Billing Portal

* Accès via controller sécurisé
* Réservé aux utilisateurs authentifiés
* `return_url` vers `/goodbye`

📌 La page `/goodbye` est :

* **100 % UX**
* **0 % métier**
* n’a **aucun impact** sur l’état abonnement

---

## 🔐 Sécurité

* Accès au dashboard protégé par :

  * rôle utilisateur
  * état abonnement (`isActive()`)
* Aucun accès accordé via redirection Stripe
* Le backend reste souverain

---

## 🧪 Tests & Debug

* Stripe CLI utilisé pour les webhooks
* Mailpit pour la validation des emails
* Tests réalisés **avec de nouveaux utilisateurs**

📌 Comportement attendu :

* un utilisateur déjà en `grace`
  → **ne reçoit plus d’email d’annulation**

---

## 🚫 Décisions techniques assumées

* Pas de migrations Doctrine automatiques
* Modifications DB manuelles contrôlées
* Pas de champ dédié pour tracer l’envoi d’email
* Garde-fous métier privilégiés à la sur-modélisation
* Stripe = **source unique de vérité**

---

## ✅ État du projet

**STATUT : STABLE – FLOW ABONNEMENT VALIDÉ**

* activation OK
* renouvellement OK
* annulation programmée OK
* désactivation finale OK
* emails maîtrisés
* accès sécurisé

Toute évolution future (upgrade, downgrade, prod Stripe, refacto)
**doit respecter strictement ces règles**.

---

*Ce document reflète l’état réel du projet AgentBoost après implémentation, debug et validation complète.*
