# Sécurité des dépendances Node.js

## Pourquoi cette simplification ?

Les outils npm servent seulement au **développement** et à la compilation. Ils ne doivent pas être déployés sur le serveur WordPress.

## Vérifications avant un nouveau projet

1. Exécuter `npm install` pour mettre à jour les dépendances et `package-lock.json` après remplacement du fichier `package.json`
2. Exécuter `npm audit`. Aucune vulnérabilité ne doit subsister pour déclarer le chantier clôturé
3. Exécuter `npm run check && npm run preprod && npm run prod`
4. Conserver ensemble les nouvelles versions de `package.json` et `package-lock.json` dans le référentiel du Starter

Les fichiers de développement et `node_modules` sont exclus des archives WordPress.
