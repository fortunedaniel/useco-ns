# Fonctions PHP ComKani et contenus administrables

## Image mentale

Le **Core** contient les mécanismes partagés. Le **projet client** fournit les données, les champs, les templates et le design. WordPress stocke les contenus ; les fonctions PHP les récupèrent. Le Core ne réalise pas automatiquement la mise en page.

## `comkani_page_field()` : récupérer un champ

Signature réelle (`packages/comkani-core/includes/editor.php`) :

```php
comkani_page_field(string $field_key, int $post_id = 0, string $page_slug = '')
```

- `field_key` : clé exacte déclarée dans le champ TypeScript (`titre`, `visuel`, etc.)
- `post_id` : ID de la page WordPress ; `0` utilise la page actuellement consultée
- `page_slug` : slug de configuration ; une chaîne vide utilise le slug de la page courante
- retour : valeur de champ, dont un entier pour une image/vidéo ; pour les groupes ou repeaters, la forme dépend du champ

Exemple de page Services :

```php
<?php
$page_id = (int) get_queried_object_id();
$title = comkani_page_field('titre', $page_id, 'services');
echo '<h1>' . esc_html((string) $title) . '</h1>';
```

## Afficher une image

Un champ `image` renvoie **un identifiant WordPress**, pas une URL. Pour un champ `visuel` :

```php
<?php
$image_id = (int) comkani_page_field('visuel', get_queried_object_id(), 'services');
if ($image_id > 0 && wp_attachment_is_image($image_id)) {
    echo wp_get_attachment_image($image_id, 'large', false, ['class' => 'services-visual']);
}
```

`wp_get_attachment_image()` construit le HTML WordPress de l'image, avec son contexte média. Pour obtenir seulement l'URL : `wp_get_attachment_image_url($image_id, 'large')`, puis l'échapper avec `esc_url()` à l'affichage. Pour les vidéos, vérifier le MIME (`get_post_mime_type`) avant d'afficher une balise `<video>` avec `esc_url(wp_get_attachment_url($id))`.

## `comkani_attachment_image()` : helper de rendu

Daniel a identifié cette fonction pour les images. Signature réelle dans `inc/component-helpers.php` :

```php
comkani_attachment_image(int $attachment_id, $size = 'full', array $attributes = []): string
```

Ce helper renvoie du HTML d'image ; il gère également les SVG. Exemple :

```php
<?php
$visuel_id = (int) comkani_page_field('visuel', get_queried_object_id(), 'services');
echo comkani_attachment_image($visuel_id, 'large', ['class' => 'services-image']);
```

La fonction renvoie une chaîne vide pour un identifiant nul. Elle ne doit pas recevoir directement une URL ni être utilisée pour une vidéo.

## Autres fonctions `comkani_*()`

Le préfixe `comkani_` est utilisé à la fois pour des **helpers utilisables dans les templates** et des **fonctions internes** (sécurité, duplication, nettoyage, administration). Ne pas appeler arbitrairement une fonction interne dans un template.

Pour chaque nouveau helper destiné à être utilisé par Daniel ou par un futur collaborateur, ajouter à sa documentation : chemin du fichier, signature PHPDoc, paramètres, type retourné, dépendances, exemple de template et limites d'utilisation. Référencer systématiquement sa source dans `packages/comkani-core/includes/` ou `inc/`.

## Dépendances, vérifications et retour arrière

- vérifier que le plugin ComKani Core est actif et que la configuration PHP a été régénérée
- utiliser la page courante ou le bon ID ; ne pas confondre slug et titre affiché
- échapper chaque sortie (`esc_html`, `esc_attr`, `esc_url` ou fonction WordPress produisant du HTML sûr)
- tester des champs vides, des images supprimées et le compte client WordPress
- ne jamais modifier directement le Core d'un client livré pour corriger le Starter maître
- revenir au commit précédent pour le code, et restaurer séparément les contenus de recette si nécessaire
