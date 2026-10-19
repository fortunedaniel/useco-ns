# Les commandes que nous utilisons

Notre objectif est de retenir peu de commandes quotidiennes. Les scripts techniques auxiliaires continuent d'exister, mais tu n'as pas besoin de les mémoriser.

| Commande | Son rôle | Effet principal |
| --- | --- | --- |
| `npm run init` | prépare l'identité du projet | aperçu ou modification explicite du fichier projet |
| `npm run dev` | travaille en mode développement | génère et surveille les ressources compilées |
| `npm run check` | vérifie la cohérence | effectue le nettoyage configuré, génère le manuel et contrôle le projet |
| `npm run build` | prépare les ressources front-end | écrit des fichiers générés et compilés |
| `npm run preprod` | prépare une archive pour recette | contrôle, compile et crée un ZIP local |
| `npm run prod` | prépare une archive de livraison | contrôle, compile et crée un ZIP local |
| `npm run docs` | ouvre le manuel interne | génère `docs/index.html` puis démarre le serveur local |

## Que signifie `internal:` ?

C'est une convention de nommage pour les sous-commandes techniques. Ce n'est pas une fonctionnalité spéciale de npm : elles restent visibles et exécutables. Nous les utilisons derrière les commandes principales.

## Avant toute commande

Nous vérifions toujours son rôle, ses fichiers cibles, ses effets indirects, ses risques et la manière de revenir en arrière. Les commandes d'exemple ne doivent jamais être exécutées avec des valeurs fictives.

## Production ne veut pas dire déploiement

Les commandes de packaging créent des fichiers à livrer. Elles ne publient pas automatiquement le site sur le serveur.
