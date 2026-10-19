# Utiliser nos composants

## Qu'est-ce qu'un composant ?

Un composant est un élément d'interface réutilisable : FAQ, chiffres clés, témoignages, logos, bouton d'action ou autre motif adapté à un site.

**Une comparaison simple :** notre bibliothèque ressemble à un ensemble de pièces de construction. Nous choisissons uniquement celles nécessaires au projet.

## Notre principe de modularité

- un composant possède une responsabilité claire
- sa configuration ne doit pas contenir de contenu hérité d'un ancien client
- sa désactivation doit éviter son chargement PHP et celui de ses ressources propres en production
- ses dépendances communes doivent rester mutualisées
- le client ne doit pas pouvoir modifier librement le système SCSS de l'agence

## Administration WordPress et gestion des contenus

Dans le Starter WordPress ComKani, nous privilégions les interfaces d'administration et les composants propriétaires développés par l'agence.

**Gutenberg n'est pas notre solution d'édition par défaut.**

- **Pages et contenus structurés :** utiliser les interfaces ComKani adaptées à chaque type de contenu.
- **Articles de blog :** Gutenberg peut être conservé lorsque son utilisation est pertinente, avec une sélection limitée de blocs autorisés.
- **Blocs natifs WordPress :** leur utilisation doit rester exceptionnelle et justifiée.
- **Données répétables :** privilégier des interfaces spécifiques, simples et ergonomiques.
- **Administration client :** limiter les options aux fonctionnalités réellement utiles pour éviter toute modification accidentelle du design.

Nous conservons une séparation claire entre la structure technique du site, les composants graphiques et les contenus administrables.

**Notre objectif est de permettre aux clients de gérer facilement leurs contenus, sans intervenir dans la structure ou la conception du site.**

## Ce qui reste à tester

La compilation conditionnelle des ressources, l'activation PHP et les scénarios d'édition par type de composant ne sont pas encore entièrement validés. Cela sera testé lors de la mise en place du prochain projet. 
