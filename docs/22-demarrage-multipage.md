# Démarrer un projet WordPress : parcours complet

Ce chapitre est le **point de départ opérationnel** pour Kani, Daniel et les futurs membres du Digital Studio. Il décrit l'ordre des opérations. La référence détaillée des pages, CPT et templates se trouve dans le chapitre « Créer des pages, sous-pages, champs et CPT ».

## Image mentale

Le **Starter** est la boîte à outils réutilisable ; **Local** contient une installation WordPress propre à un projet ; **Sublime Text** permet d'éditer les fichiers du Starter ; le **générateur** traduit la configuration en PHP ; le **setup WordPress** synchronise volontairement les pages et menus avec la base locale ; les **templates** produisent l'affichage public. Ces étapes ne sont pas interchangeables.

## Avant de commencer : ce que tu prépares

1. un site WordPress neuf dans **Local** et une sauvegarde de la base avant la première synchronisation
2. une branche Git ou une copie de travail propre au projet, en conservant le Starter maître intact
3. le thème ComKani à l'emplacement `app/public/wp-content/themes/comkani-starter`
4. Node.js et npm disponibles **uniquement sur l'ordinateur de développement**
5. le Core ComKani chargé, dans la version compatible avec le thème

**Attention :** travailler dans le dossier d'un projet client, jamais dans le Starter maître pour personnaliser des contenus. Les modifications de la base WordPress ne sont pas annulées par Git.

## Étape 1. Définir l'identité et le Design System du projet

Dans `config/src/project.ts`, renseigner les informations du client et les paramètres du projet. Dans `config/tokens/`, remplacer les tokens Figma du projet (couleurs, typographie et autres fondations). Pour les couleurs, conserver au minimum `primary` et `secondary` ; ajouter `tertiary`, `quaternary` ou toute autre famille nécessaire, avec la même structure entre Light et Dark.

**Résultat attendu :** les fichiers sources décrivent ce projet sans reprendre l'identité d'un ancien client. **À ne pas faire :** modifier à la main les fichiers SCSS générés.

## Étape 2. Déclarer les pages et les sous-pages

Dans `config/src/wordpress.ts`, modifier `pages`. Exemple pédagogique :

```ts
pages: [
    {title: 'Accueil', slug: 'accueil', home: true},
    {title: 'Services', slug: 'services'},
    {title: 'Maintenance', slug: 'maintenance', parent: 'services'},
    {title: 'Contrats', slug: 'contrats', parent: 'maintenance'},
    {title: 'Contact', slug: 'contact'},
    {title: 'Actualités', slug: 'actualites'},
],
```

Ici, `Maintenance` est **une sous-page de Services**, et `Contrats` une sous-page de Maintenance. `parent` agit sur la hiérarchie des pages WordPress, **pas sur le menu**.

**Résultat attendu après setup :** les pages existent dans l'administration. **Ne fait pas :** créer leur design ni renseigner leurs contenus.

## Étape 3. Déclarer le menu principal et ses sous-menus

Toujours dans `config/src/wordpress.ts`, modifier `menus`. Exemple :

```ts
menus: {
    primary: [
        {page: 'accueil', label: 'Accueil'},
        {page: 'services', label: 'Nos services', children: [
            {page: 'maintenance', label: 'Maintenance', children: [
                {page: 'contrats', label: 'Contrats de maintenance'},
            ]},
        ]},
        {url: '/realisations/', label: 'Réalisations'},
        {page: 'contact', label: 'Contact'},
    ],
    footer: {
        navigation: {
            label: 'Navigation',
            items: [{page: 'services'}, {page: 'contact'}],
        },
    },
    legal: [],
},
```

Le tableau `primary` contient les liens du menu. La propriété `children` définit le **sous-menu** et peut être imbriquée. `page` doit correspondre à un slug déclaré dans `pages`. Pour un lien externe : `{url: 'https://exemple.fr', label: 'Partenaire'}`. Un sous-menu n'exige **pas** que sa page soit déclarée comme sous-page, même si les deux coïncident souvent.

**Résultat attendu après setup :** menu principal et sous-menus créés ou synchronisés. **Attention :** une nouvelle synchronisation peut réécrire les entrées gérées ; elle ne nettoie pas systématiquement les anciens liens.

## Étape 4. Déclarer un CPT uniquement si nécessaire

Un CPT (*Custom Post Type*) représente une collection de contenus répétitifs : réalisations, collaborateurs, événements, etc. **Ne pas utiliser un CPT pour une simple page de présentation.**

Toujours dans `config/src/wordpress.ts` :

```ts
postTypes: [
    {
        key: 'realisation',
        label: 'Réalisations',
        singular: 'Réalisation',
        slug: 'realisations',
        archive: true,
        icon: 'dashicons-portfolio',
    },
],
```

**Résultat attendu :** le Core enregistre le type `realisation` dans WordPress, permettant de créer ensuite des fiches. **Ne fait pas :** créer les fiches ni les templates `single-realisation.php` et `archive-realisation.php`. L'URL `/realisations/` est ici réservée à l'archive du CPT. C'est pourquoi **aucune page classique `realisations` n'est déclarée** à l'étape 2. Si tu préfères une page classique, il faut revoir la stratégie d'archive CPT pour éviter le conflit d'URL. Le lien du menu `/realisations/` est une URL relative, à vérifier dans WordPress après synchronisation.

## Étape 5. Définir les champs administrables des pages

Créer, dans le projet, `config/src/pages/Services.ts` :

```ts
import {defineEditorPage} from '../../framework/editor.ts';

export default defineEditorPage({
    label: 'Page Services',
    groups: [{
        key: 'introduction',
        label: 'Introduction',
        fields: [
            {key: 'titre', label: 'Titre', type: 'text'},
            {key: 'description', label: 'Description', type: 'textarea'},
            {key: 'visuel', label: 'Visuel', type: 'image'},
        ],
    }],
});
```

Puis importer explicitement ce fichier dans `config/src/index.ts` et l'enregistrer dans `pageEditor.pages` avec la clé `services`. Les fichiers placés dans `config/src/pages/` **ne sont pas découverts automatiquement**. Cette déclaration prépare des champs pour l'administration, mais pas leur affichage sur le site public.

## Étape 6. Générer et vérifier le projet

À la racine du thème, et uniquement dans l'environnement de développement :

```bash
npm ci
npm run internal:typecheck
npm run internal:generate
npm run build
```

- `npm ci` installe les dépendances de développement selon le lockfile et reconstruit `node_modules` ; aucun besoin en production
- `internal:typecheck` contrôle le typage sans fabriquer de livraison
- `internal:generate` écrit les fichiers générés, notamment `config/generated/project-config.php` et les SCSS de fondation
- `build` compile les assets ; vérifier les différences Git après exécution

**Vérifier :** aucune erreur terminal et aucun changement inexpliqué dans les fichiers générés. **Retour arrière :** restaurer les sources depuis Git ; les sorties générées seront recalculées à la prochaine génération.

## Étape 7. Synchroniser volontairement WordPress dans Local

Vérifier que le terminal est ouvert dans **le thème du bon site Local**, et sauvegarder d'abord la base. Une fois la configuration générée et la cible vérifiée, exécuter :

```bash
npm run internal:wp-setup
```

Cette commande agit sur la **base WordPress du site Local cible** : pages, paramètres et menus configurés. Elle ne se contente pas de générer des fichiers. Il ne faut pas la lancer directement sur une production ou une base contenant des éléments éditoriaux à préserver. Contrôler ensuite les pages dans **Pages**, la navigation dans l'administration WordPress et les CPT disponibles.

**Retour arrière :** Git ne restaure pas la base ; utiliser la sauvegarde Local prise avant synchronisation.

## Étape 8. Développer les templates et renseigner les contenus

Les pages et CPT sont maintenant déclarés, mais leur design doit être réalisé. Créer les templates WordPress nécessaires au projet (par exemple `page-services.php`, `single-realisation.php`, `archive-realisation.php`) et les composants SCSS/PHP associés. Dans le back-office, renseigner les champs de la page et créer les fiches de CPT.

**Vérifier :** navigation desktop et mobile, sous-menus accessibles au clavier, liens sans erreur 404, rendu des pages, archives et fiches, sauvegarde et réouverture des champs.

## Étape 9. Contrôle de fin de mise en place

- pages et sous-pages présentes avec les bons slugs et parents
- sous-menus et liens externes corrects, sans doublons
- archive CPT et page classique sans conflit d'URL
- champs administrables disponibles et sauvegardés
- templates et contenus visibles en front-office
- TypeScript, génération, build et recette WordPress réussis

**Ce qui reste manuel :** création graphique, contenu éditorial, QA finale et décisions SEO. Le Starter automatise les fondations répétitives ; il ne fabrique pas un site client complet sans développement.
