# Préproduction, livraison et mise en ligne

## Environnements à ne pas confondre

- Starter source : code de référence de l'agence
- Local : WordPress de développement sur le Mac
- Recette : installation distincte pour valider un ZIP et vérifier l'absence de dépendances cachées
- Production : hébergement du site client, par exemple OVH ou IONOS, avec la configuration propre au client

## Procédure avant livraison

1. Confirmer le périmètre du projet et la version du Starter
2. Sauvegarder le code et les données de l'environnement concerné
3. Exécuter les contrôles techniques disponibles et corriger leurs erreurs
4. Générer les ressources de production et l'archive prévue par `package.json`
5. Installer cette archive sur l'environnement de recette distinct
6. Tester les écrans publics, l'administration, les droits, les formulaires et les flux essentiels
7. Vérifier les réglages spécifiques au client et les secrets hors du dépôt
8. Établir un procès-verbal de recette et identifier les réserves avant le déploiement

## Principes d'exploitation

Les outils Node.js et Vite ne sont pas requis sur le serveur WordPress lorsque les fichiers finaux ont été générés. Ne jamais considérer la création d'un ZIP comme un déploiement. Documenter le plan de retour arrière : archive précédente, sauvegarde de base et médias, vérification de la restauration.
