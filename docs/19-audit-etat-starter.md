# État technique du Starter ComKani

Ce document conserve un état de travail daté. Les procédures courantes se trouvent dans les chapitres principaux du manuel. En cas de divergence, vérifier le code et la version actuelle du Starter avant de réutiliser une indication historique.

## Architecture

Les configurations sont dans `config/src`. Les ressources communes du front sont dans `resources/ts/main.ts`. `vite.config.ts` détermine les modules de composants à inclure à partir de `components`.

## Activation

Le Starter dispose de composants réutilisables que nous activons selon les besoins de chaque projet.

Le fichier `config/src/features/components.ts` centralise leur configuration.

Lorsqu'un composant est activé, nous chargeons uniquement les ressources nécessaires à son fonctionnement : PHP pour son intégration à WordPress et TypeScript lorsqu'une interaction JavaScript est requise.

Les composants désactivés ne doivent pas être inclus inutilement dans les fichiers JavaScript de production.

**Notre objectif :** disposer d'une bibliothèque de composants ComKani réutilisable, tout en conservant des sites légers, performants et adaptés aux besoins de chaque client.

## État des tests du Starter WordPress ComKani

Le Starter ComKani a déjà fait l'objet de plusieurs vérifications techniques et de premiers tests fonctionnels dans Local.

Vérifications déjà effectuées :
- Installation d'un WordPress vierge sur un environnement de recette indépendant
- Activation du thème et de ComKani Core
- Compilation des ressources SCSS et TypeScript avec Vite.
- Exécution des contrôles techniques du Starter
- Premières vérifications de l'administration ComKani et du journal d'activité

Points nécessitant une validation complémentaire :
- Édition des pages après les derniers ajustements des interfaces ComKani
- Restauration des contenus depuis le journal d'activité
- Duplication des contenus et vérification des données associées
- Envoi, réception et traitement des différents formulaires
- Vérification des permissions selon les rôles utilisateurs.
- Recette complète des composants activés
- Installation et fonctionnement sur un hébergement WordPress classique.

Nous distinguons les contrôles techniques réussis des fonctionnalités validées en conditions réelles.

Une fonctionnalité déjà testée doit être vérifiée à nouveau lorsqu'une modification du code peut affecter son comportement.

**L'objectif n'est pas de recommencer les tests inutilement, mais de finaliser les validations manquantes et de contrôler les éventuelles régressions.**

## Sécurité

Ne jamais intégrer des identifiants SMTP dans le dépôt, le guide ou les archives diffusables.

## Image mentale

Le thème est la structure visible, ComKani Core fournit les fonctionnalités transversales et Vite prépare uniquement les ressources nécessaires au projet.
