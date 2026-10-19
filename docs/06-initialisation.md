# Initialiser un nouveau projet

L’initialisation du Starter ComKani remplace l’identité de la configuration source. **Elle n’est pas un nettoyage de la base de données WordPress.** 

## Fonctionnement

`npm run init` accepte un nom et un slug. Sans option d’application, le script fournit un aperçu. Une application explicite sauvegarde l’ancien `config/src/project.ts` dans `.comkani-backups/initialization/`, puis écrit la nouvelle configuration.

Le sérialiseur intégré produit une syntaxe TypeScript conforme aux conventions du projet : clés sans guillemets inutiles et chaînes délimitées par des apostrophes simples.

## Ce qui reste à faire

Le Starter WordPress ComKani constitue la base technique de nos nouveaux projets clients. Nous pouvons l'utiliser pour initialiser un site comme US&CO, en créant une installation WordPress indépendante et en personnalisant son identité, ses composants et son Design System.

La validation complète de la V1 reste un chantier distinct. Avant toute livraison client, nous contrôlons les fonctionnalités réellement utilisées et effectuons la recette du site concerné.

## Retour arrière

Une sauvegarde est créée avant écriture. Pour annuler une initialisation, restaurer la sauvegarde du fichier source puis relancer les contrôles adaptés.
