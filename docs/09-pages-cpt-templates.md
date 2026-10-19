# Créer des pages, sous-pages, champs et CPT

## Image mentale

`config/src/wordpress.ts` décrit la structure du site. `config/src/pages/` peut décrire les champs administrables. `config/src/index.ts` assemble les données. Le générateur transforme cette configuration en PHP. `scripts/wp-setup.php` crée ou synchronise les pages et menus dans la base **uniquement quand tu l'exécutes volontairement sur Local**. Enfin, les templates PHP affichent les contenus.

**Cinq opérations différentes** : déclarer une page, créer la page en base, définir des champs, écrire le template et remplir les contenus dans WordPress. Faire la première ne réalise pas automatiquement les cinq.

## 1. Créer la structure des pages

Fichier : `config/src/wordpress.ts`. Dans le tableau `pages` :

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

`title` est visible dans WordPress ; `slug` est la clé URL stable ; `parent` crée une **sous-page** WordPress. Une sous-page n'est pas automatiquement un sous-menu. Le programme synchronise les parents avant les enfants, y compris à plusieurs niveaux. Déclarer des slugs uniques ; ne jamais modifier un slug publié sans plan de redirection SEO.

## 2. Créer les menus et sous-menus

Toujours dans `config/src/wordpress.ts`, remplir `menus` :

```ts
menus: {
    primary: [
        {page: 'accueil', label: 'Accueil'},
        {page: 'services', label: 'Nos services', children: [
            {page: 'maintenance', label: 'Maintenance', children: [
                {page: 'contrats', label: 'Contrats de maintenance'},
            ]},
        ]},
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

`children` représente des **sous-menus** : le setup utilise l'identifiant d'élément de menu WordPress (`menu-item-parent-id`). Un `parent` dans `pages` et un `children` dans `menus` sont indépendants. `page` référence un `slug` déclaré, `url` crée un lien personnalisé, `legal` référence une page juridique activée. Pour un lien extérieur : `{url: 'https://exemple.fr', label: 'Partenaire'}`.

Le setup ajoute ou met à jour les liens gérés et affecte les emplacements, mais **ne supprime pas les anciennes entrées**. Une modification manuelle du menu WordPress peut être réécrite par une synchronisation. Ne pas relancer ce setup sur un site client sans vérifier le résultat et sauvegarder la base.

## 3. Ajouter des champs administrables à la page

Fichier : `config/src/pages/Services.ts` (à créer pour ton projet, pas pour le Starter neutre) :

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
            {key: 'video', label: 'Vidéo', type: 'video'},
        ],
    }],
});
```

**Attention : le Starter n'importe pas automatiquement les fichiers de `pages/`.** Pour relier ce schéma, adapter explicitement `config/src/index.ts` :

```ts
import servicesEditor from './pages/Services.ts';

// Dans l'objet config existant, remplacer uniquement la propriété pageEditor :
pageEditor: {...editor.pageEditor, pages: {services: servicesEditor}},
```

La clé `services` correspond au slug de page, sans majuscule. Ce changement expose les champs dans WordPress après génération de la configuration et chargement de ComKani Core. Créer le dossier `config/src/pages/` s'il n'existe pas. Ne pas remplacer les autres imports ou propriétés de `config/src/index.ts`.

## 4. Afficher les champs dans le template PHP

Les champs sont stockés par le Core et ne sont pas automatiquement mis en page sur le site public. Prévoir le template WordPress approprié, par exemple `page-services.php` selon la hiérarchie des templates WordPress, et respecter les helpers réellement disponibles.

```php
<?php
/* Template PHP du projet, illustration du principe, à adapter au thème. */
get_header();
$page_id = get_queried_object_id();
$titre = function_exists('comkani_page_field')
    ? comkani_page_field('titre', $page_id, 'services')
    : '';
?>
<main><h1><?php echo esc_html((string) $titre); ?></h1></main>
<?php get_footer(); ?>
```

**Signature confirmée :** `comkani_page_field(string $field_key, int $post_id = 0, string $page_slug = '')`. Une image correspond à un identifiant de pièce jointe, et non nécessairement à son URL. Pour une image, privilégier `wp_get_attachment_image($id, 'large')` après contrôle de l'identifiant et du type. Un rendu de vidéo nécessite son propre balisage et un contrôle de l'URL.

## 5. Créer un CPT (un type de contenu métier)

Un CPT convient à une collection structurée et répétée, comme des réalisations, des équipes ou des produits éditoriaux. Ne pas créer un CPT pour une simple page Services.

Dans `config/src/wordpress.ts` :

```ts
postTypes: [
    {
        key: 'reference',
        label: 'Références',
        singular: 'Référence',
        slug: 'references',
        archive: true,
        icon: 'dashicons-portfolio',

        labels: {
            addNew: 'Ajouter une référence',
            editItem: 'Modifier la référence',
            allItems: 'Toutes les références',
        },

        taxonomies: {
            categories: {
                enabled: true,
                label: 'Catégories de références',
                singular: 'Catégorie de référence',
                slug: 'categories-references',
            },
            tags: {
                enabled: true,
                label: 'Étiquettes de références',
                singular: 'Étiquette de référence',
                slug: 'etiquettes-references',
            },
        },
    },
],
```

Le Core enregistre le CPT à l'événement WordPress `init` ; ses contenus se créent ensuite dans l'administration WordPress. Pour l'affichage, construire `archive-realisation.php` et `single-realisation.php` **dans le projet client**, uniquement si une présentation particulière est requise. Le Core actuel n'associe pas automatiquement des groupes de champs `defineEditorPage()` aux CPT : cette extension nécessiterait un travail distinct.

## 6. Vérifier le flux complet, sans toucher à la production

1. conserver les fichiers précédents dans Git et sauvegarder la base Local
2. exécuter `npm run internal:typecheck`, puis `npm run internal:generate` depuis la racine du thème local
3. regarder les modifications de `config/generated/project-config.php`
4. vérifier **le site Local actif**, puis exécuter `npm run internal:wp-setup` seulement si la synchronisation voulue est comprise
5. dans WordPress : contrôler Pages, Apparence > Menus (si accessible), l'éditeur de Services et le CPT Réalisations
6. tester un parent, deux niveaux de sous-pages, un sous-menu, un champ et l'affichage PHP ; inspecter aussi le rendu mobile et clavier du thème

**Retour arrière** : restaurer les fichiers depuis le commit Git précédent. Les changements de pages/menus déjà synchronisés dans WordPress sont en base de données : Git ne les annule pas. Restaurer la sauvegarde Local adaptée si nécessaire, sans réinitialiser un site client.

## Ce qui est automatique et ce qui ne l'est pas

| Action | Automatique ? |
|---|---|
| générer la configuration PHP à partir des sources | oui, avec la commande de génération |
| créer les pages et menus en base | oui, mais uniquement en lançant volontairement le setup Local |
| appliquer les sous-menus `children` | oui, après setup, dans la version corrigée |
| créer l'interface des champs d'une page configurée | oui, via le Core chargé dans WordPress |
| créer le design et les templates PHP | non, travail de développement |
| créer le contenu éditorial d'un CPT | non, administrable par le client |
| créer automatiquement des champs pour chaque CPT | non, non pris en charge par ce flux |
