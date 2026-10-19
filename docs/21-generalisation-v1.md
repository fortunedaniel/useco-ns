# Généraliser le Starter ComKani

## Notre objectif

Nous préparons une base WordPress réutilisable : aucun futur projet ne doit hériter de l’identité ou des contenus d’un précédent site.

## Ce que nous avons préparé

- identité source : `ComKani Starter` / `comkani-starter`
- nom du package npm : `comkani-wordpress-starter`
- configuration WordPress minimale et neutre
- composants déclarés désactivés par défaut
- modèle éditorial de page d’accueil spécifique retiré de la configuration centrale

## État des validations technique

Travaux déjà réalisés :
- Réinitialisation de la base WordPress de développement et réinstallation d'un WordPress propre
- Suppression des anciens médias clients identifiés
- Création d'une installation WordPress de recette indépendante
- Activation et premiers contrôles du thème et de ComKani Core
- Nettoyage de plusieurs fichiers obsolètes du Starter
- Vérification du fonctionnement de la compilation Vite
- Exécution réussie des contrôles techniques et des commandes de préparation des livrables sur une version antérieure

Travaux partiellement validés :
- Neutralisation des templates et ressources : une dernière recherche exhaustive des références aux anciens projets reste nécessaire
- ComKani Core : plusieurs interfaces ont été testées, mais les derniers correctifs nécessitent une recette ciblée.
- Préproduction et production : les procédures de génération ont été vérifiées, mais pas le déploiement complet sur un serveur distant

Validations restant à effectuer :
- Vérifier que seuls les composants activés sont intégrés aux ressources compilées
- Finaliser les scénarios de duplication et contrôler les données associées
- Tester la restauration des contenus depuis le journal d'activité
- Valider les formulaires et les permissions utilisateurs.
- Vérifier les dernières modifications apportées aux interfaces d'édition
- Effectuer une recette complète sur un environnement représentatif de la production

Nous conservons les résultats des contrôles déjà réalisés. Nous ne répétons un test que lorsqu'une évolution du code peut entraîner une régression ou lorsque sa validation précédente est insuffisamment documentée.

## Image mentale

Le starter est une boîte à outils fermée et rangée. Lorsqu’un projet commence, nous choisissons les outils nécessaires : nous ne conservons pas les contenus ni les réglages d’un ancien client.
