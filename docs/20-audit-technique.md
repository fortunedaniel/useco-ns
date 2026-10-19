# Audit technique et recette du starter

## Contrôles vérifiés dans le code

- Le Core charge le module de duplication depuis `packages/comkani-core/comkani-core.php`
- Le module n'ajoute le lien que si `duplication.enabled`, le type de contenu et les permissions sont valides
- Le chargement PHP des composants est conditionnel dans `inc/components.php` pour contact, booking, FAQ et WhatsApp
- Le point d'entrée Vite inclut conditionnellement les modules TypeScript de booking, FAQ et témoignages. La correspondance entre tous les composants PHP, TS et SCSS reste à contrôler composant par composant
- Le moteur de packaging exclut explicitement `node_modules`, `scripts`, `resources`, `docs`, et les sources de configuration

## Points non validés automatiquement

- Tests WordPress de bout en bout : duplication, rôles, permissions, formulaires, cache, consentement et notifications
- Sécurité npm : l'installation locale a signalé 19 vulnérabilités (1 modérée, 16 élevées, 2 critiques). Les packages et chemins de dépendance affectés doivent être identifiés à partir d'un `npm audit --json` à jour. Aucune résolution ni innocuité ne sont présumées
- Packages `npm run preprod` et `npm run prod` : leur exécution et l'inspection de leurs ZIP doivent être réussies avant livraison. Les scripts peuvent produire des archives dans `release/` mais ne déploient pas le thème
- Neutralisation exhaustive des héritages client dans tous les modules et médias : audit complémentaire nécessaire

## Préproduction et production

Les commandes `npm run preprod` et `npm run prod` exécutent d'abord les vérifications du projet, puis produisent les archives. Ne pas lancer de déploiement depuis le starter sans revue du package et validation de la Direction.

Ne mettre aucune donnée de pilotage interne ou financière dans ce guide. Ne pas publier ce guide sur un site client.
