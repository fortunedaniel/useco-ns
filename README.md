# Agence ComKani | Starter WordPress

**Thème WordPress : 1.2.0 | ComKani Core : 1.9.0 | 10 octobre 2026**

**Statut : consolidation technique en cours, recette fonctionnelle à finaliser.**

Le Starter WordPress ComKani est notre base de développement propriétaire pour la création de sites WordPress professionnels, administrables, performants et évolutifs.

Il permet de démarrer un nouveau projet client, comme US&CO, à partir d'une architecture commune, sans reconstruire systématiquement les fonctionnalités et composants déjà développés.

Le Starter est conçu pour être réutilisable, multipage et indépendant des données de nos clients.

## 1. Organisation des environnements

| Environnement | Rôle |
|---|---|
| Starter WordPress | Code source commun, composants, configuration et outils |
| Local : `starter-wordpress` | Environnement principal de développement |
| Local : `comkani-starter-recette` | Installation WordPress indépendante destinée aux tests |
| DDEV | Futur environnement de développement reproductible, migration à préparer |
| Préproduction | Validation du site dans des conditions proches de la production |
| Production | Hébergement WordPress du client, notamment OVH ou IONOS |
| GitHub | Gestion des versions du code et collaboration entre Kani et Daniel |

Les outils Node.js, npm et Vite sont nécessaires au développement et à la préparation des ressources, mais ne sont pas requis pour faire fonctionner le site WordPress en production.

**Le Starter n'est pas un site client.** Chaque nouveau projet doit disposer de sa propre installation WordPress, de sa base de données et de ses contenus.

## 2. Architecture technique

Notre architecture repose sur :

- **WordPress et PHP** pour le fonctionnement du site et l'administration.
- **ComKani Core** pour les fonctionnalités propriétaires et les interfaces d'administration.
- **SCSS** pour le système de styles et le Design System.
- **TypeScript strict** pour les interactions nécessitant JavaScript.
- **Vite** pour la compilation des ressources.
- **Git et GitHub** pour le suivi des modifications et la collaboration.
- **Sublime Text** comme environnement principal d'édition.

React n'est utilisé que lorsqu'il apporte une réelle valeur interactive. Il n'est pas requis pour le fonctionnement général du Starter.

### Structure principale

| Dossier ou fichier | Fonction |
|---|---|
| `components/` | Composants réutilisables ComKani |
| `config/src/` | Configuration du projet et activation des fonctionnalités |
| `config/tokens/` | Variables du Design System |
| `config/generated/` | Configuration produite automatiquement |
| `resources/scss/` | Styles sources du thème |
| `resources/ts/` | Interactions TypeScript |
| `packages/comkani-core/` | Fonctionnalités propriétaires WordPress |
| `inc/` | Modules PHP du thème |
| `templates/` | Templates PHP spécifiques |
| `scripts/` | Outils de développement, contrôles et livraison |
| `docs/` | Documentation technique et manuel HTML |
| `assets/build/` | Ressources publiques compilées par Vite |
| `release/` | Archives de livraison générées localement |

## 3. Premier démarrage

Avant toute manipulation, vérifier que nous travaillons dans le bon dossier et sur le bon environnement WordPress.

1. Ouvrir `comkani-starter` dans Sublime Text.
2. Vérifier la version de Node.js définie dans `.nvmrc`.
3. Installer les dépendances avec `npm ci`.
4. Exécuter `npm run check` pour contrôler les sources et générer la documentation.
5. Démarrer le développement avec `npm run dev`.
6. Ouvrir le site WordPress local et vérifier les modifications.
7. Arrêter le processus avec `Ctrl + C` lorsque la session de développement est terminée.

### Commandes essentielles

| Commande | Utilisation |
|---|---|
| `npm ci` | Installer les dépendances selon `package-lock.json` |
| `npm run dev` | Compiler les ressources et surveiller les modifications |
| `npm run check` | Exécuter les vérifications configurées du Starter |
| `npm run internal:typecheck` | Vérifier les types TypeScript |
| `npm run docs` | Consulter le manuel selon le script du projet |
| `npm run preprod` | Préparer une archive destinée à la recette |
| `npm run prod` | Préparer l'archive de production |

Les commandes de livraison ne remplacent pas les tests fonctionnels dans WordPress.

## 4. Documentation et Live Reload

Les fichiers Markdown situés dans `docs/` sont les sources de notre documentation.

Le fichier `docs/index.html` est généré automatiquement. Nous ne le modifions pas directement.

Pour travailler sur la documentation avec actualisation automatique :

```bash
node scripts/docs/dev.cjs
```

Ouvrir ensuite :

`http://127.0.0.1:4179/`

À chaque enregistrement d'un Markdown, le manuel est régénéré et le navigateur peut s'actualiser automatiquement lorsque le serveur fonctionne.

Le Live Reload du thème est distinct de celui du manuel.

## 5. Règles de développement ComKani

### Composants et administration

Nous privilégions les composants et interfaces propriétaires ComKani.

Pour les pages classiques, l'objectif est de proposer des champs et sections structurés, sans exposer inutilement les éditeurs natifs WordPress.

Les pages légales conservent une édition de texte adaptée.

Gutenberg peut être utilisé pour les articles uniquement lorsque l'agence le juge pertinent. Les blocs natifs restent exceptionnels.

### Standards de code

- Utiliser des noms de fichiers, classes et fonctions cohérents.
- Privilégier des composants réutilisables.
- Appliquer TypeScript strict et éviter `any` sans justification.
- Ajouter des en-têtes et une documentation PHPDoc ou JSDoc pertinents aux fichiers sources concernés.
- Ne pas imposer d'en-têtes documentaires aux fichiers SCSS.
- Séparer clairement configuration, données, présentation et interactions.
- Ne pas introduire de dépendance inutile.
- Vérifier les permissions, les données utilisateur et les opérations sensibles.

Le code du Starter et de ComKani Core doit rester indépendant des informations propres à un client.

## 6. Initialisation d'un nouveau projet client

Le Starter constitue notre base technique commune.

Pour initialiser un nouveau projet, nous devons notamment :

1. Créer une installation WordPress indépendante.
2. Préparer une copie de travail du Starter.
3. Définir l'identité et les paramètres du projet.
4. Adapter les tokens du Design System.
5. Activer les fonctionnalités et composants nécessaires.
6. Préparer les pages et contenus du client.
7. Effectuer les contrôles techniques et fonctionnels.

La configuration de l'identité ne constitue pas, à elle seule, l'initialisation complète du site.

Le Starter maître doit rester neutre et réutilisable.

## 7. Qualité, sécurité et validation

### Vérifications déjà réalisées

- Réinitialisation de l'ancien WordPress de développement.
- Installation WordPress indépendante pour la recette.
- Activation du thème et de ComKani Core.
- Nettoyage de fichiers obsolètes identifiés.
- Compilation Vite et contrôles techniques réussis sur des versions précédentes.
- Premiers essais fonctionnels de l'administration.

### Validations à finaliser

- Contrôler les dernières modifications de l'éditeur ComKani.
- Vérifier les permissions selon les rôles.
- Finaliser la recette des formulaires et de la duplication.
- Vérifier les possibilités de restauration du journal d'activité.
- Tester la compilation sélective des composants.
- Exécuter une recette complète sur les archives de préproduction et de production.
- Valider le fonctionnement sur un hébergement WordPress représentatif.

La présence d'une fonctionnalité dans le code ne constitue pas une preuve de validation fonctionnelle.

Les résultats doivent être documentés et les régressions vérifiées après chaque modification importante.

## 8. GitHub et gestion des versions

Dépôt privé de référence :

**`agencecomkani/comkani-wordpress-starter`**

Branche principale : `main`.

Chaque modification significative doit être enregistrée dans un commit explicite.

Avant un envoi sur GitHub, nous vérifions qu'aucun secret, fichier de configuration local sensible, contenu client ou fichier temporaire n'est ajouté.

Le dépôt contient les sources du Starter et sa documentation. Il ne remplace pas les sauvegardes des bases de données WordPress.

Les changements importants doivent pouvoir être retrouvés, expliqués et annulés.

## 9. Préproduction et livraison

Avant toute livraison, nous devons :

1. Exécuter les contrôles techniques.
2. Préparer l'archive de préproduction.
3. Tester cette archive sur une installation WordPress indépendante.
4. Contrôler l'administration et les fonctionnalités réellement utilisées.
5. Corriger les anomalies et documenter les résultats.
6. Préparer l'archive de production après validation.
7. Effectuer les vérifications nécessaires après déploiement.

Les dépendances de développement Node.js et npm ne doivent pas être nécessaires au fonctionnement du site livré.

## 10. Références documentaires

La documentation de référence est disponible dans `docs/index.html`.

Les principaux chapitres comprennent :

- Le préambule et la prise en main du Starter
- L'architecture et la configuration technique
- Le Design System et TypeScript
- Les pages, CPT, templates et composants
- ComKani Core et son administration
- Les procédures de qualité, de recette et de dépannage.
- La préparation des livraisons.
- Git, maintenance et évolution du Starter.
- L'inventaire des fichiers et les références d'audit.

**Référentiel d'inventaire :** `docs/27-inventaire-fichiers.md`.

Les documents d'historique décrivent les travaux et décisions passés. Ils ne remplacent pas les procédures techniques actuelles.

---

**Agence ComKani | Digital Studio**

*Starter WordPress propriétaire. Version de travail consolidée du 10 octobre 2026. Recette fonctionnelle en cours.*