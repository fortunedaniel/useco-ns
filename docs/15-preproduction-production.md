# Préproduction et production

## Deux étapes, deux objectifs

**La préproduction** permet de tester un livrable sur un environnement dédié (serveur mutualisé de l'agence) avant sa mise en ligne.

**La production** correspond au site public utilisé par les visiteurs.

## Comment nous fabriquons les packages

Les deux commandes passent par un moteur de packaging partagé. La différence d'environnement doit rester explicite dans la commande et dans la procédure de validation.

## Ce que les ZIP doivent contenir

Les fichiers PHP du thème, les ressources CSS et JavaScript compilées et les configurations générées nécessaires. Les sources TypeScript, SCSS, Node et la documentation interne doivent rester hors du package client.

## Ce que les commandes ne font pas

Préparer une archive n'est pas déployer le site. La publication réelle, la synchronisation des contenus et la restauration de la base sont des opérations distinctes.

## Vérifications avant livraison

- inspecter l'archive et son contenu
- installer le livrable sur une installation WordPress de test
- vérifier l'affichage, les formulaires, les composants et l'administration
- conserver une version précédente permettant un retour arrière

La validation fonctionnelle complète des packages du starter reste à réaliser.
