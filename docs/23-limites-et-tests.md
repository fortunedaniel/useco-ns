# Limites et vérifications avant adoption

Ce dossier fournit une **base générique en cours de validation**, pas une recette fonctionnelle WordPress achevée.

## Contrôles réalisés

- intégrité des chemins de configuration et existence des modules
- syntaxe des fichiers TypeScript modifiés par analyse statique
- syntaxe des fichiers PHP par `php -l`

## Vérifications à réaliser dans Local

1. installation sur une base WordPress neuve avec un thème au nom technique prévu
2. `npm ci` et `npm run check` depuis le Mac de développement
3. `npm run build`, rendu des pages et test de la navigation
4. activation de chaque composant et comparaison des fichiers compilés
5. tests des fonctions de ComKani Core, de la duplication et des droits utilisateur
6. tests de préproduction et production sur un environnement isolé
7. examen des composants, templates, SCSS et médias hérités avant utilisation client

## Ce que ce travail ne fait pas

Il ne nettoie pas la base du site WordPress cloné dans Local, ne modifie aucun secret et ne teste pas automatiquement les formulaires ni l’administration WordPress. Les styles anciens et certains modules restent à généraliser.
