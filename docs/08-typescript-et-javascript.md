# TypeScript et JavaScript

## Notre principe

Nous utilisons TypeScript lorsqu'il permet de rendre une interaction plus fiable et plus compréhensible. WordPress et PHP restent responsables du rendu et de l'administration côté serveur. React n'est envisagé que lorsqu'une interface interactive le justifie réellement.

## Développement et production

Pendant le développement, Node.js et Vite compilent les sources front-end. Sur un hébergement WordPress classique, le site doit utiliser les ressources compilées : les visiteurs et le serveur ne doivent pas exécuter Vite ou installer npm.

## Procédure

1. Rechercher un module ou comportement déjà existant avant d'en créer un nouveau
2. Préférer des types explicites aux conversions implicites; éviter `any` sans justification documentée. Dans le Starter ComKani, nous utilisons TypeScript pour détecter les erreurs de données pendant le développement. Nous définissons clairement les types lorsque cela améliore la compréhension du code. Nous vérifions les données provenant des formulaires et des API avant de les utiliser. Nous évitons any, car il désactive les contrôles de TypeScript. Si son utilisation est exceptionnellement nécessaire, nous expliquons pourquoi dans un commentaire. Notre objectif est de produire un code fiable, compréhensible et facilement maintenable par l'équipe.
3. Écrire un JSDoc décrivant le rôle, les entrées, les sorties et les effets de bord des fonctions publiques
4. Lancer les vérifications de types avec la commande du projet puis tester dans le navigateur. Après une modification TypeScript, nous lançons npm run internal:typecheck depuis le dossier du thème afin de détecter les erreurs de typage. Si aucune erreur n'est signalée, nous testons ensuite la fonctionnalité dans le navigateur, sur le site WordPress local. Ces deux contrôles sont complémentaires : TypeScript vérifie la cohérence du code, tandis que le navigateur permet de vérifier son fonctionnement réel.
5. Vérifier le comportement sans JavaScript si la fonctionnalité doit rester accessible par dégradation progressive. Lorsque cela est pertinent, nous concevons les fonctionnalités essentielles pour qu'elles restent utilisables même si JavaScript ne se charge pas. JavaScript doit améliorer l'expérience utilisateur sans rendre inutilement les contenus ou les actions essentielles dépendants de son fonctionnement. Après le développement, nous désactivons temporairement JavaScript dans le navigateur pour vérifier le comportement des fonctionnalités concernées. Les composants qui nécessitent obligatoirement JavaScript doivent être identifiés et documentés.

## Ce qui n'est pas compilé par Vite

Les fichiers PHP du thème et de ComKani Core, ainsi que les scripts et styles autonomes du Core, ont leur propre circuit de chargement. Modifier `admin.css` ne crée donc pas nécessairement de nouvelle ligne `vite build`.

## En cas d'erreur TypeScript

Commencer par lire l'erreur et vérifier les sources réelles. Si des dossiers parasites apparaissent dans `node_modules/@types` après des copies Finder, réinstaller avec `npm ci` depuis le thème, après avoir vérifié que le fichier `package-lock.json` est disponible. Cette commande reconstruit `node_modules` et ne modifie pas la base WordPress.
