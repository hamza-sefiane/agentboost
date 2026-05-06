# Gestion des abonnements avec Stripe et suppression différée des comptes

## Fonctionnalités clés
- Résiliation d’abonnement programmée via Stripe.
- Annulation de la résiliation avant la fin de la période.
- Suppression des comptes utilisateurs après expiration.

## Routes principales
- `/subscription/cancel` : Programme la résiliation d'un abonnement.
- `/subscription/cancel-cancellation` : Annule la résiliation programmée.
- `/account/delete` : Programme la suppression du compte à la fin de l’abonnement.
- `/account/delete/cancel` : Annule la suppression du compte programmée.

## Comportement des webhooks Stripe
- `customer.subscription.updated` : Met à jour l'état de résiliation ou annule la résiliation si nécessaire.
- `customer.subscription.deleted` : Peut déclencher une suppression utilisateur (fallback).

## Automatisation de la suppression
- Commande : `php bin/console app:delete-expired-users`.
- Supprime les utilisateurs dont la période est expirée.

## Sécurité et cohérence
- Stripe est la source de vérité.
- Les webhooks synchronisent l’état.
- La suppression réelle est principalement faite via une commande (cron).
- Le webhook peut agir en fallback.

## Étapes pour tester (mode test)
1. Souscrire via Stripe
2. Résilier : `/subscription/cancel`
3. Annuler résiliation : `/subscription/cancel-cancellation`
4. Supprimer compte : `/account/delete`
5. Annuler suppression : `/account/delete/cancel`
6. Lancer : `php bin/console app:delete-expired-users`

---

## 📊 Diagramme du flow abonnement & suppression

```mermaid
flowchart TD

A[Utilisateur] -->|Souscription| B[Stripe Checkout]

B -->|Paiement OK| C[Webhook: invoice.payment_succeeded]
C --> D[activateSubscription()]
D --> E[User actif]

E -->|Clique résilier| F[POST /subscription/cancel]
F --> G[Stripe: cancel_at_period_end = true]

G --> H[Webhook: customer.subscription.updated]
H --> I[markCancellationAtPeriodEnd()]
I --> J[Etat: grace]

J -->|Annuler résiliation| K[POST /subscription/cancel-cancellation]
K --> L[Stripe: cancel_at_period_end = false]
L --> M[Webhook: updated]
M --> D

J -->|Demande suppression compte| N[POST /account/delete]
N --> O[markDeletionAtPeriodEnd()]

O --> P[Attente fin période]

P --> Q[CRON: delete-expired-users]
Q --> R[Suppression User + data]

H -->|Event final| S[Webhook: subscription.deleted]
S -->|fallback| R

```
---
