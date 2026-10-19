# Documentation du Starter ComKani

Nous rédigeons les contenus du manuel dans les fichiers `.md` du dossier `docs/`. Le fichier `index.html` est généré automatiquement et ne doit jamais être édité directement.

## Parcours principal

Le manuel présente un seul sommaire organisé par étapes : commencer, configurer, construire, administrer, contrôler, livrer et faire évoluer. Les documents historiques et les registres de validation sont regroupés sous « Archives et références » et restent consultables sans être confondus avec la procédure courante.

## Régénérer le manuel

Depuis la racine du thème, exécuter la commande documentée dans `package.json` (`npm run docs` pour ouvrir le manuel ou `npm run internal:docs-generate` pour la génération seule si ce script est présent).

## Règles éditoriales

- Un sujet opérationnel a une rubrique principale identifiable.
- Les archives ne sont pas une seconde procédure parallèle.
- Éviter les tirets longs dans les titres et les textes.
- Distinguer les faits vérifiés, les recommandations et les contrôles restant à réaliser.
- Ne jamais inclure des données client, mots de passe ou secrets.

## Ordre du sommaire

Les fichiers numerotes suivent strictement leur ordre de lecture dans le manuel HTML. Les chapitres de reference viennent apres les procedures principales. Le nom du fichier n’apparait pas au-dessus du titre.
