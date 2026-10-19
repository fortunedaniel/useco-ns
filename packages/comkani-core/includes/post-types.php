<?php
// =============================================================================
// Fichier : packages/comkani-core/includes/post-types.php
// Rôle : enregistrer les CPT et leurs taxonomies propres au projet
//
// Ce qu'il fait :
// - enregistre les CPT configurés depuis config/src/wordpress.ts
// - personnalise les libellés de l'administration
// - enregistre les catégories et étiquettes propres à chaque CPT
//
// Ce qu'il ne fait pas :
// - ne crée, ne supprime et ne migre aucun contenu existant
// - ne modifie pas les catégories et étiquettes des articles natifs
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Enregistre les types de contenus personnalisés et les taxonomies associées.
 *
 * @return void
 */
function comkani_register_project_post_types(): void
{
    $post_types = (array) comkani_core_get('postTypes', []);
    foreach ($post_types as $config) {
        if (!is_array($config)) {
            continue;
        }

        $key = sanitize_key((string) ($config['key'] ?? ''));
        $label = sanitize_text_field((string) ($config['label'] ?? ''));
        $singular = sanitize_text_field((string) ($config['singular'] ?? $label));
        $slug = sanitize_title((string) ($config['slug'] ?? $key));
        if ($key === '' || $label === '' || strlen($key) > 20) {
            continue;
        }

        $custom_labels = is_array($config['labels'] ?? null) ? $config['labels'] : [];
        $labels = [
            'name' => $label,
            'singular_name' => $singular,
            'add_new' => __('Ajouter', 'comkani'),
            'add_new_item' => sprintf('Ajouter une nouvelle %s', $singular),
            'edit_item' => sprintf('Modifier %s', $singular),
            'new_item' => sprintf('Nouvelle %s', $singular),
            'all_items' => sprintf('Toutes les %s', $label),
        ];
        if (!empty($custom_labels['addNew'])) {
            $labels['add_new_item'] = sanitize_text_field((string) $custom_labels['addNew']);
        }
        if (!empty($custom_labels['editItem'])) {
            $labels['edit_item'] = sanitize_text_field((string) $custom_labels['editItem']);
        }
        if (!empty($custom_labels['allItems'])) {
            $labels['all_items'] = sanitize_text_field((string) $custom_labels['allItems']);
        }

        register_post_type($key, [
            'labels' => $labels,
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => !empty($config['archive']),
            'rewrite' => ['slug' => $slug],
            'menu_icon' => sanitize_key((string) ($config['icon'] ?? 'dashicons-admin-post')),
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'],
        ]);

        $taxonomies = is_array($config['taxonomies'] ?? null) ? $config['taxonomies'] : [];
        foreach (['categories' => true, 'tags' => false] as $family => $hierarchical) {
            $taxonomy = $taxonomies[$family] ?? null;
            if (!is_array($taxonomy) || empty($taxonomy['enabled'])) {
                continue;
            }

            // Identifiants stables, distincts des slugs publics configurables.
            $taxonomy_key = $key . ($hierarchical ? '_category' : '_tag');
            if (strlen($taxonomy_key) > 32) {
                _doing_it_wrong(__FUNCTION__, 'La clé de taxonomie dépasse 32 caractères.', '1.0.0');
                continue;
            }

            $taxonomy_label = sanitize_text_field((string) ($taxonomy['label'] ?? ''));
            $taxonomy_singular = sanitize_text_field((string) ($taxonomy['singular'] ?? ''));
            $taxonomy_slug = sanitize_title((string) ($taxonomy['slug'] ?? $taxonomy_key));
            if ($taxonomy_label === '' || $taxonomy_singular === '' || $taxonomy_slug === '') {
                continue;
            }

            register_taxonomy($taxonomy_key, [$key], [
                'labels' => [
                    'name' => $taxonomy_label,
                    'singular_name' => $taxonomy_singular,
                    'search_items' => sprintf('Rechercher dans %s', $taxonomy_label),
                    'all_items' => $taxonomy_label,
                    'edit_item' => sprintf('Modifier %s', $taxonomy_singular),
                    'add_new_item' => sprintf('Ajouter %s', $taxonomy_singular),
                ],
                'public' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'show_in_rest' => true,
                'hierarchical' => $hierarchical,
                'rewrite' => ['slug' => $taxonomy_slug],
            ]);
        }
    }
}

add_action('init', 'comkani_register_project_post_types', 20);
