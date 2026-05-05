# AgentBoost – Documentation Technique

AgentBoost est une plateforme SaaS dédiée aux agents immobiliers, automatisant la gestion des abonnements et la suppression d’utilisateurs en fin de période.

## Gestion des Abonnements via Stripe

### Souscription
1. L’utilisateur souscrit via Stripe Checkout.
2. Après paiement, Stripe envoie les webhooks : `invoice.payment_succeeded` et `customer.subscription.created`. L’application active l’abonnement avec `$user->activateSubscription($periodEnd)`.

### Résiliation d’Abonnement
1. L’utilisateur clique sur "Résilier" (Route : POST /subscription/cancel).
2. On appelle Stripe : `Subscription::update($id, ['cancel_at_period_end' => true])`.
3. Pas de modification immédiate en base. 4. Stripe enverra un webhook `customer.subscription.updated`. 5. L’application marque la résiliation en fin de période : `$user->markCancellationAtPeriodEnd($date)`.

### Annulation de la Résiliation
1. L’utilisateur annule la résiliation (Route : POST /subscription/cancel-cancellation).
2. On appelle Stripe : `Subscription::update($id, ['cancel_at_period_end' => false])`.
3. Webhook `customer.subscription.updated` reçu. 4. L’abonnement est réactivé côté application : `$user->activateSubscription($periodEnd)`.

## Suppression d’Utilisateur

### Demande de suppression
1. L’utilisateur demande la suppression (Route : POST /account/delete).
2. On résilie via Stripe en fin de période : `cancel_at_period_end = true`.
3. L’application marque l’utilisateur pour suppression : `$user->markDeletionAtPeriodEnd($date)`.

### Annulation de la Suppression
1. L’utilisateur annule la suppression (Route : POST /account/delete/cancel). 2. L’application annule le marquage : `$user->unmarkDeletionAtPeriodEnd()`.

### Suppression Automatique (CRON)
1. Une commande exécute périodiquement `php bin/console app:delete-expired-users`.
2. Si la date est atteinte et l’utilisateur marqué, il est supprimé : `$em->remove($user)`.

## Webhooks Stripe (Source de Vérité)
- `invoice.payment_succeeded` : Active l’abonnement.
- `customer.subscription.updated` : Met à jour les statuts.
- `customer.subscription.deleted` : Désactive ou supprime si suppression programmée.

## Sécurité et Idempotence
- Chaque event Stripe est stocké (éviter les doublons).
- On logge les erreurs mais on retourne toujours HTTP 200 pour éviter les retries infinis.

## Commandes Utiles
- `php bin/console app:delete-expired-users` : Suppression programmée.
- `php bin/console debug:router` : Liste des routes.
- `php bin/console doctrine:schema:update --force` : Met à jour la base.

## Conclusion
L’architecture est alignée aux bonnes pratiques SaaS :
- Stripe = Source de vérité
- Webhooks = Synchronisation
- Commandes = Actions critiques

Base solide, prête à être améliorée.