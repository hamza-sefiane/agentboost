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
- `customer.subscription.deleted` : Supprime l’utilisateur si la suppression était programmée.

## Automatisation de la suppression
- Commande : `php bin/console app:delete-expired-users`.
- Cette commande supprime les utilisateurs dont l'abonnement est arrivé à terme et dont la suppression a été programmée.

## Sécurité et cohérence
- Aucun changement en base de données dans les contrôleurs ou webhooks concernant des suppressions. Les actions critiques sont toujours traitées par la commande dédiée.

## Étapes pour tester (en mode test)
1. Active un abonnement et simule une résiliation avec `/subscription/cancel`.
2. Annule la résiliation via `/subscription/cancel-cancellation`.
3. Programme la suppression de compte via `/account/delete`.
4. Annule la suppression de compte via `/account/delete/cancel`.
5. Lance la commande `php bin/console app:delete-expired-users` pour vérifier la suppression des comptes expirés.

## Remarque finale
Ce système assure que la gestion des abonnements est fiable. En mode production, assure-toi d’avoir des logs pour chaque suppression et d’éviter toute suppression irréversible directement via un webhook.